<?php
/**
 * Histórico Escolar oficial — layout aproximado SED-SP (8 campos).
 * Paisagem A4. Tipografia formal (sem cores de marca).
 *
 * Variáveis: $titulo, $dados (aluno, unidade, itens, resultados, observacoes_gerais),
 *            $documento, $assinaturas, $validation_url, $resultado_labels,
 *            $logo_data, $cidade_data, $gerado_em
 */
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
$itens = is_array($dados['itens'] ?? null) ? $dados['itens'] : [];
$resultados = is_array($dados['resultados'] ?? null) ? $dados['resultados'] : [];
$obsGerais = trim((string) ($dados['observacoes_gerais'] ?? $documento['observacoes_gerais'] ?? ''));
$assinaturas = is_array($assinaturas ?? null) ? $assinaturas : [];
$labels = is_array($resultado_labels ?? null) ? $resultado_labels : [];
$logoData = (string) ($logo_data ?? '');
$validationUrl = (string) ($validation_url ?? '');
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!class_exists('StudentFormHelper', false)) {
    require_once dirname(__DIR__, 3) . '/Helpers/StudentFormHelper.php';
}
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$fmtDataExtenso = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    if (!$dt) {
        return '';
    }
    $meses = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];
    return (int) $dt->format('j') . ' de ' . $meses[(int) $dt->format('n')] . ' de ' . $dt->format('Y');
};

$nomeUnidade = trim((string) ($unidade['razao_social'] ?? '')) ?: trim((string) ($unidade['nome'] ?? 'Instituição de Ensino'));
$cidadeUe = trim((string) ($unidade['cidade'] ?? ''));
$ufUe = trim((string) ($unidade['uf'] ?? ''));
$munUf = trim($cidadeUe . ($ufUe !== '' ? ' / ' . $ufUe : ''));
$linhaEndereco = trim(implode(', ', array_filter([
    trim((string) ($unidade['endereco'] ?? '')) . (trim((string) ($unidade['numero'] ?? '')) !== '' ? ', ' . $unidade['numero'] : ''),
    trim((string) ($unidade['bairro'] ?? '')),
    $munUf,
    trim((string) ($unidade['cep'] ?? '')) !== '' ? 'CEP ' . $unidade['cep'] : '',
])));
$telefoneUe = trim((string) ($unidade['telefone'] ?? $unidade['fone'] ?? ''));
$emailUe = trim((string) ($unidade['email'] ?? $unidade['email_institucional'] ?? ''));
$atos = array_filter([
    trim((string) ($unidade['ato_autorizacao'] ?? '')) !== '' ? 'Ato de autorização/criação: ' . $unidade['ato_autorizacao'] : '',
    trim((string) ($unidade['ato_credenciamento'] ?? '')) !== '' ? 'Credenciamento: ' . $unidade['ato_credenciamento'] : '',
    trim((string) ($unidade['ato_reconhecimento'] ?? '')) !== '' ? 'Reconhecimento: ' . $unidade['ato_reconhecimento'] : '',
]);
$linhaDocsUe = trim(implode(' · ', array_filter([
    trim((string) ($unidade['cnpj'] ?? '')) !== '' ? 'CNPJ ' . $unidade['cnpj'] : '',
    trim((string) ($unidade['inep'] ?? '')) !== '' ? 'Código INEP/MEC ' . $unidade['inep'] : '',
])));

// Agrupa trajetória por ano|série
$porAno = [];
foreach ($itens as $it) {
    $ano = (string) ($it['ano_letivo'] ?? '');
    $serie = (string) ($it['serie_ano'] ?? '');
    $k = $ano . '|' . $serie;
    if (!isset($porAno[$k])) {
        $porAno[$k] = ['ano' => $ano, 'serie' => $serie, 'itens' => []];
    }
    $porAno[$k]['itens'][] = $it;
}
ksort($porAno);

$resultMap = [];
foreach ($resultados as $r) {
    $resultMap[(string) ($r['ano_letivo'] ?? '') . '|' . (string) ($r['serie_ano'] ?? '')] = $r;
}

// CAMPO 4 — estudos realizados (série × ano × estabelecimento)
$estudos = [];
foreach ($porAno as $bloco) {
    $escolasBloco = [];
    foreach ($bloco['itens'] as $it) {
        $escOrig = trim((string) ($it['escola_origem'] ?? ''));
        if ($escOrig === '') {
            $escOrig = $nomeUnidade;
        }
        $escolasBloco[$escOrig] = true;
    }
    foreach (array_keys($escolasBloco) as $nomeEsc) {
        $estudos[] = [
            'serie' => $bloco['serie'],
            'ano' => $bloco['ano'],
            'estabelecimento' => $nomeEsc,
            'municipio_uf' => ($nomeEsc === $nomeUnidade) ? ($munUf ?: '—') : '—',
            'resultado' => $resultMap[$bloco['ano'] . '|' . $bloco['serie']] ?? null,
        ];
    }
}

$dirNome = trim((string) ($unidade['diretor_nome'] ?? ''));
$dirReg = trim((string) ($unidade['diretor_registro'] ?? ''));
$secNome = trim((string) ($unidade['secretario_nome'] ?? ''));
$secReg = trim((string) ($unidade['secretario_registro'] ?? ''));
$dirAssinadoEm = '';
$secAssinadoEm = '';
foreach ($assinaturas as $a) {
    if (($a['cargo'] ?? '') === 'Diretor' && trim((string) ($a['usuario_nome'] ?? '')) !== '') {
        $dirNome = (string) $a['usuario_nome'];
        if (!empty($a['numero_registro'])) {
            $dirReg = (string) $a['numero_registro'];
        }
        $dirAssinadoEm = (string) ($a['assinado_em'] ?? '');
    }
    if (($a['cargo'] ?? '') === 'Secretario_Escolar' && trim((string) ($a['usuario_nome'] ?? '')) !== '') {
        $secNome = (string) $a['usuario_nome'];
        if (!empty($a['numero_registro'])) {
            $secReg = (string) $a['numero_registro'];
        }
        $secAssinadoEm = (string) ($a['assinado_em'] ?? '');
    }
}

$hashFull = trim((string) ($documento['hash_validacao'] ?? ''));
$hashCurto = $hashFull !== '' ? substr($hashFull, 0, 16) . '…' : '';
$numeroRegistroSed = trim((string) ($documento['numero_registro_sed'] ?? $dados['numero_registro_sed'] ?? ''));
$qrImg = '';
if ($validationUrl !== '') {
    $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=' . rawurlencode($validationUrl);
}

$raAluno = trim((string) ($aluno['ra'] ?? ''));
if ($raAluno === '') {
    $raAluno = trim((string) ($aluno['codigo_aluno'] ?? $aluno['codigo'] ?? ''));
}
$rgAluno = trim((string) ($aluno['rg'] ?? ''));
$rneAluno = trim((string) ($aluno['rne'] ?? ''));
$finalidade = (string) ($dados['finalidade'] ?? $documento['finalidade'] ?? 'Solicitacao');

// Observação padrão SP (escala) — se ainda não constar no texto livre
$obsEscalaSp = 'A partir de 2007 — Escala numérica de notas de 0 (zero) a 10 (dez), '
    . 'com desempenho escolar satisfatório igual ou superior a 05 (cinco).';
$mostrarEscala = (stripos($obsGerais, 'Escala numérica') === false);

$certificacao = '';
if ($finalidade === 'Conclusao') {
    $certificacao = 'Certifico que o(a) aluno(a) acima identificado(a) concluiu os estudos nos termos da legislação vigente, '
        . 'conforme trajetória escolar transcrita neste histórico.';
} elseif ($finalidade === 'Transferencia') {
    $certificacao = 'Declaro que o(a) aluno(a) acima identificado(a) cursou neste estabelecimento de ensino as séries/anos '
        . 'e componentes curriculares ora transcritos, para fins de transferência.';
} else {
    $certificacao = 'Declaro que o(a) aluno(a) acima identificado(a) cursou neste estabelecimento de ensino as séries/anos '
        . 'e componentes curriculares ora transcritos, conforme solicitação.';
}

$emitidoEm = '';
if (!empty($documento['emitido_em'])) {
    $emitidoEm = $fmtData(substr((string) $documento['emitido_em'], 0, 10));
}
if ($emitidoEm === '' || $emitidoEm === '—') {
    $emitidoEm = $fmtData(date('Y-m-d'));
}
$cidadeDataTxt = trim((string) ($cidade_data ?? ''));
if ($cidadeDataTxt === '') {
    $cidadeDataTxt = ($cidadeUe !== '' ? $cidadeUe : 'Local') . ', ' . $fmtDataExtenso(date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= $esc($titulo ?? 'Histórico Escolar') ?></title>
    <style>
        @page { margin: 10mm 9mm 12mm 9mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #111;
            font-size: 8pt;
            margin: 0;
            line-height: 1.25;
        }
        .campo { margin: 0 0 7px 0; border: 1px solid #222; }
        .campo-tit {
            background: #e8e8e8;
            border-bottom: 1px solid #222;
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 3px 6px;
        }
        .campo-body { padding: 5px 6px; }
        .cabecalho { width: 100%; border-collapse: collapse; }
        .cabecalho td { vertical-align: middle; padding: 0; }
        .logo { max-height: 48px; max-width: 60px; }
        .ue-nome { font-size: 11pt; font-weight: bold; margin: 0 0 2px 0; text-transform: uppercase; }
        .ue-linha { font-size: 7pt; margin: 0 0 1px 0; }
        .gov { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; margin: 0 0 2px 0; }
        .doc-titulo {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 6px 0 8px 0;
            border: 1.5px solid #111;
            padding: 5px 4px;
        }
        table.grid { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 2px 4px;
            vertical-align: top;
        }
        table.grid th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 6.5pt;
        }
        table.grid td.c { text-align: center; }
        table.grid td.num { text-align: center; font-weight: bold; }
        table.ident { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.ident td { padding: 2px 4px; vertical-align: top; }
        table.ident td.lab { width: 16%; font-weight: bold; white-space: nowrap; }
        .base-legal { font-size: 7.5pt; margin: 0 0 5px 0; }
        .ano-bloco { margin: 6px 0 0 0; }
        .ano-tit {
            font-size: 8pt;
            font-weight: bold;
            background: #f5f5f5;
            border: 1px solid #333;
            border-bottom: none;
            padding: 2px 5px;
        }
        .resultado-ano { font-size: 7.5pt; margin: 2px 0 4px 0; padding: 0 2px; }
        .obs-txt { font-size: 7.5pt; margin: 0; white-space: pre-wrap; }
        .cert { font-size: 8pt; text-align: justify; margin: 0; }
        .assinaturas { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .assinaturas td { width: 50%; text-align: center; vertical-align: bottom; padding: 18px 12px 0 12px; }
        .assinaturas .line { border-top: 1px solid #111; width: 85%; margin: 0 auto 3px auto; padding-top: 3px; }
        .assinaturas .cargo { font-size: 7pt; }
        .fecho { text-align: right; font-size: 8pt; margin: 8px 0 0 0; }
        .qr { width: 64px; height: 64px; }
        .footer {
            position: fixed;
            bottom: -6mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6pt;
            color: #555;
        }
        .muted { color: #444; font-size: 6.5pt; }
        .small { font-size: 7pt; }
    </style>
</head>
<body>

    <!-- CAMPO 1 — Cabeçalho -->
    <div class="campo">
        <div class="campo-tit">Campo 1 — Cabeçalho / Identificação da Unidade Escolar</div>
        <div class="campo-body">
            <table class="cabecalho">
                <tr>
                    <td style="width:68px;">
                        <?php if ($logoData !== ''): ?>
                            <img class="logo" src="<?= $esc($logoData) ?>" alt="Logo">
                        <?php endif; ?>
                    </td>
                    <td style="padding-left:8px;">
                        <?php if (strtoupper($ufUe) === 'SP'): ?>
                            <p class="gov">Estado de São Paulo</p>
                        <?php elseif ($ufUe !== ''): ?>
                            <p class="gov">Estado — <?= $esc($ufUe) ?></p>
                        <?php endif; ?>
                        <p class="ue-nome"><?= $esc($nomeUnidade) ?></p>
                        <?php if ($linhaEndereco !== ''): ?><p class="ue-linha"><?= $esc($linhaEndereco) ?></p><?php endif; ?>
                        <?php if ($telefoneUe !== '' || $emailUe !== ''): ?>
                            <p class="ue-linha">
                                <?= $telefoneUe !== '' ? $esc('Tel.: ' . $telefoneUe) : '' ?>
                                <?= ($telefoneUe !== '' && $emailUe !== '') ? ' · ' : '' ?>
                                <?= $emailUe !== '' ? $esc('E-mail: ' . $emailUe) : '' ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($linhaDocsUe !== ''): ?><p class="ue-linha"><?= $esc($linhaDocsUe) ?></p><?php endif; ?>
                        <?php foreach ($atos as $ato): ?><p class="ue-linha"><?= $esc($ato) ?></p><?php endforeach; ?>
                    </td>
                    <td style="width:76px; text-align:right;">
                        <?php if ($qrImg !== ''): ?>
                            <img class="qr" src="<?= $esc($qrImg) ?>" alt="QR">
                            <div class="muted">Validação online</div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="doc-titulo"><?= $esc($titulo ?? 'Histórico Escolar') ?></div>

    <!-- CAMPO 2 — Identificação do aluno -->
    <div class="campo">
        <div class="campo-tit">Campo 2 — Identificação do Aluno</div>
        <div class="campo-body">
            <table class="ident">
                <tr>
                    <td class="lab">Nome completo:</td>
                    <td colspan="3"><?= \StudentFormHelper::nomeOficialHtml($aluno, $esc) ?></td>
                </tr>
                <tr>
                    <td class="lab">RG / RNE:</td>
                    <td><?php
                        $docsId = array_filter([
                            $rgAluno !== '' ? 'RG ' . $rgAluno : '',
                            $rneAluno !== '' ? 'RNE ' . $rneAluno : '',
                            trim((string) ($aluno['cpf'] ?? '')) !== '' ? 'CPF ' . $aluno['cpf'] : '',
                        ]);
                        echo $esc($docsId ? implode(' · ', $docsId) : '—');
                    ?></td>
                    <td class="lab">RA:</td>
                    <td><?= $esc($raAluno !== '' ? $raAluno : '—') ?></td>
                </tr>
                <tr>
                    <td class="lab">Nascimento:</td>
                    <td><?= $esc($fmtData($aluno['data_nasc'] ?? '')) ?></td>
                    <td class="lab">Município / UF / País:</td>
                    <td><?php
                        $nat = array_filter([
                            trim((string) ($aluno['naturalidade'] ?? '')),
                            trim((string) ($aluno['uf_nascimento'] ?? '')),
                            trim((string) ($aluno['nacionalidade'] ?? '')) !== ''
                                ? (string) $aluno['nacionalidade']
                                : 'Brasil',
                        ]);
                        echo $esc($nat ? implode(' / ', $nat) : '—');
                    ?></td>
                </tr>
                <tr>
                    <td class="lab">Filiação:</td>
                    <td colspan="3"><?php
                        $fil = array_filter([
                            trim((string) ($aluno['nome_mae'] ?? '')) !== '' ? 'Mãe: ' . $aluno['nome_mae'] : '',
                            trim((string) ($aluno['nome_pai'] ?? '')) !== '' ? 'Pai: ' . $aluno['nome_pai'] : '',
                        ]);
                        echo $esc($fil ? implode(' · ', $fil) : '—');
                    ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- CAMPO 3 — Base legal e trajetória -->
    <div class="campo">
        <div class="campo-tit">Campo 3 — Base Legal e Transcrição da Trajetória Escolar</div>
        <div class="campo-body">
            <p class="base-legal">
                <strong>Base legal:</strong>
                Lei de Diretrizes e Bases da Educação Nacional nº 9.394/96 e normas estaduais aplicáveis ao Ensino Fundamental e Médio.
                <?php if ($atos): ?>
                    <?= $esc(' · ' . implode(' · ', $atos)) ?>
                <?php endif; ?>
            </p>

            <?php if ($porAno === []): ?>
                <p class="muted">Nenhum componente curricular registrado neste histórico.</p>
            <?php else: ?>
                <?php foreach ($porAno as $bloco): ?>
                    <?php
                    $k = $bloco['ano'] . '|' . $bloco['serie'];
                    $res = $resultMap[$k] ?? null;
                    $resLabel = $res ? ($labels[$res['resultado']] ?? $res['resultado']) : '';
                    $chTotal = 0;
                    foreach ($bloco['itens'] as $itCh) {
                        if (isset($itCh['carga_horaria']) && $itCh['carga_horaria'] !== null && $itCh['carga_horaria'] !== '') {
                            $chTotal += (int) $itCh['carga_horaria'];
                        }
                    }
                    ?>
                    <div class="ano-bloco">
                        <div class="ano-tit">
                            <?= $esc($bloco['serie']) ?> — Ano letivo <?= $esc($bloco['ano']) ?>
                            <?php if ($chTotal > 0): ?>
                                · Carga horária total: <?= (int) $chTotal ?>h
                            <?php endif; ?>
                        </div>
                        <table class="grid">
                            <thead>
                                <tr>
                                    <th style="width:32%;">Componente curricular / Matriz</th>
                                    <th style="width:10%;">Nota / Conceito</th>
                                    <th style="width:10%;">Carga horária</th>
                                    <th style="width:10%;">Frequência %</th>
                                    <th style="width:10%;">Origem</th>
                                    <th>Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bloco['itens'] as $it): ?>
                                    <tr>
                                        <td><?= $esc($it['componente'] ?? '') ?></td>
                                        <td class="num"><?= $esc($it['resultado_valor'] ?? '—') ?></td>
                                        <td class="c"><?= isset($it['carga_horaria']) && $it['carga_horaria'] !== null && $it['carga_horaria'] !== ''
                                            ? (int) $it['carga_horaria'] . 'h' : '—' ?></td>
                                        <td class="c"><?= isset($it['frequencia_percentual']) && $it['frequencia_percentual'] !== null && $it['frequencia_percentual'] !== ''
                                            ? $esc(number_format((float) $it['frequencia_percentual'], 1, ',', '.')) . '%' : '—' ?></td>
                                        <td class="c"><?= $esc($it['origem'] ?? 'Interno') ?></td>
                                        <td class="small"><?= $esc(trim((string) ($it['parecer_descritivo'] ?? '')) ?: '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($res): ?>
                            <div class="resultado-ano">
                                <strong>Resultado do ano/série:</strong> <?= $esc($resLabel) ?>
                                <?php if (!empty($res['observacao'])): ?>
                                    — <?= $esc($res['observacao']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- CAMPO 4 — Estudos realizados -->
    <div class="campo">
        <div class="campo-tit">Campo 4 — Estudos Realizados</div>
        <div class="campo-body">
            <?php if ($estudos === []): ?>
                <p class="muted">Sem registros de estudos realizados.</p>
            <?php else: ?>
                <table class="grid">
                    <thead>
                        <tr>
                            <th style="width:18%;">Série / Ano</th>
                            <th style="width:10%;">Ano civil</th>
                            <th style="width:38%;">Estabelecimento de ensino</th>
                            <th style="width:18%;">Município / UF</th>
                            <th>Resultado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudos as $e): ?>
                            <?php
                            $rl = $e['resultado']
                                ? ($labels[$e['resultado']['resultado']] ?? $e['resultado']['resultado'])
                                : '—';
                            ?>
                            <tr>
                                <td class="c"><?= $esc($e['serie']) ?></td>
                                <td class="c"><?= $esc($e['ano']) ?></td>
                                <td><?= $esc($e['estabelecimento']) ?></td>
                                <td class="c"><?= $esc($e['municipio_uf']) ?></td>
                                <td class="c"><?= $esc($rl) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- CAMPO 5 — Observações -->
    <div class="campo">
        <div class="campo-tit">Campo 5 — Observações</div>
        <div class="campo-body">
            <?php if ($mostrarEscala): ?>
                <p class="obs-txt"><?= $esc($obsEscalaSp) ?></p>
            <?php endif; ?>
            <?php if ($obsGerais !== ''): ?>
                <p class="obs-txt" style="margin-top:4px;"><?= $esc($obsGerais) ?></p>
            <?php elseif (!$mostrarEscala): ?>
                <p class="muted">Sem observações adicionais.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- CAMPO 6 — Número de registro -->
    <div class="campo">
        <div class="campo-tit">Campo 6 — Número de Registro</div>
        <div class="campo-body">
            <table class="ident">
                <tr>
                    <td class="lab">Nº SED / GDAE:</td>
                    <td><?= $esc($numeroRegistroSed !== '' ? $numeroRegistroSed : '—') ?>
                        <span class="muted">(preencher para concluintes da rede estadual, quando houver)</span>
                    </td>
                    <td class="lab">Cód. autenticidade:</td>
                    <td><?= $esc($hashCurto !== '' ? $hashCurto : '—') ?></td>
                </tr>
                <tr>
                    <td class="lab">Versão / Finalidade:</td>
                    <td colspan="3">
                        v<?= (int) ($documento['versao'] ?? 1) ?>
                        · <?= $esc($finalidade) ?>
                        <?php if (!empty($documento['status'])): ?>
                            · <?= $esc($documento['status']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- CAMPO 7 — Certificação -->
    <div class="campo">
        <div class="campo-tit">Campo 7 — Certificação / Declaração do Diretor</div>
        <div class="campo-body">
            <p class="cert"><?= $esc($certificacao) ?></p>
            <p class="fecho"><?= $esc($cidadeDataTxt) ?>.</p>
        </div>
    </div>

    <!-- CAMPO 8 — Rodapé / Assinaturas -->
    <div class="campo">
        <div class="campo-tit">Campo 8 — Data de Expedição, Identificação e Assinaturas</div>
        <div class="campo-body">
            <p class="small" style="margin:0 0 4px 0;">
                <strong>Data da emissão:</strong> <?= $esc($emitidoEm) ?>
                <?php if ($dirAssinadoEm !== '' || $secAssinadoEm !== ''): ?>
                    · <strong>Assinado eletronicamente em:</strong>
                    <?= $esc($fmtData(substr($dirAssinadoEm ?: $secAssinadoEm, 0, 10))) ?>
                <?php endif; ?>
            </p>
            <table class="assinaturas">
                <tr>
                    <td>
                        <div class="line"></div>
                        <div><strong><?= $esc($secNome ?: ' ') ?></strong></div>
                        <div class="cargo">
                            Secretário(a) Escolar
                            <?= $secReg !== '' ? $esc(' · Reg./RG ' . $secReg) : '' ?>
                        </div>
                        <div class="muted">Nome completo, documento, cargo, carimbo e assinatura</div>
                    </td>
                    <td>
                        <div class="line"></div>
                        <div><strong><?= $esc($dirNome ?: ' ') ?></strong></div>
                        <div class="cargo">
                            Diretor(a)
                            <?= $dirReg !== '' ? $esc(' · Reg./RG ' . $dirReg) : '' ?>
                        </div>
                        <div class="muted">Nome completo, documento, cargo, carimbo e assinatura</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        Documento emitido eletronicamente em <?= $esc($gerado_em ?? date('d/m/Y')) ?> · Layout aproximado ao modelo SED-SP (EducaTudo).
        <?php if ($validationUrl !== ''): ?> Validação: <?= $esc($validationUrl) ?><?php endif; ?>
    </div>
</body>
</html>
