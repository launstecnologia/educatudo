<?php
/**
 * Cabeçalho compartilhado das declarações (dompdf).
 * Variáveis disponíveis no escopo (extraídas pelo controller):
 * $titulo, $dados['unidade'], $logo_data, $numero, $ano
 */
$unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
$logoData = (string) ($logo_data ?? '');
$nomeUnidade = trim((string) ($unidade['razao_social'] ?? '')) ?: trim((string) ($unidade['nome'] ?? ''));
if ($nomeUnidade === '') {
    $nomeUnidade = 'Instituição de Ensino';
}
$linhaEndereco = trim(implode(', ', array_filter([
    trim((string) ($unidade['endereco'] ?? '')) . (trim((string) ($unidade['numero'] ?? '')) !== '' ? ', ' . $unidade['numero'] : ''),
    trim((string) ($unidade['bairro'] ?? '')),
    trim(trim((string) ($unidade['cidade'] ?? '')) . (trim((string) ($unidade['uf'] ?? '')) !== '' ? ' / ' . $unidade['uf'] : '')),
    trim((string) ($unidade['cep'] ?? '')) !== '' ? 'CEP ' . $unidade['cep'] : '',
])));
$linhaDocs = trim(implode(' • ', array_filter([
    trim((string) ($unidade['cnpj'] ?? '')) !== '' ? 'CNPJ: ' . $unidade['cnpj'] : '',
    trim((string) ($unidade['inep'] ?? '')) !== '' ? 'INEP: ' . $unidade['inep'] : '',
    trim((string) ($unidade['telefone'] ?? '')) !== '' ? 'Tel.: ' . $unidade['telefone'] : '',
])));
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= $esc($titulo ?? 'Declaração') ?></title>
    <style>
        @page { margin: 22mm 20mm 22mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 11pt; margin: 0; line-height: 1.6; }
        .header { display: table; width: 100%; border-bottom: 2px solid #064e3b; padding-bottom: 10px; margin-bottom: 6px; }
        .header .logo-cell { display: table-cell; width: 90px; vertical-align: middle; }
        .header .title-cell { display: table-cell; vertical-align: middle; padding-left: 12px; }
        .header img { max-height: 64px; max-width: 84px; }
        .header .escola { font-size: 13pt; font-weight: bold; color: #064e3b; margin: 0 0 2px 0; }
        .header .meta { font-size: 8.5pt; color: #4b5563; margin: 1px 0; }
        .doc-num { text-align: right; font-size: 8.5pt; color: #6b7280; margin: 6px 0 18px 0; }
        h1.doc-title { text-align: center; font-size: 15pt; color: #111827; letter-spacing: 1px; text-transform: uppercase; margin: 10px 0 26px 0; }
        .corpo { text-align: justify; font-size: 11.5pt; margin: 0 4px; }
        .corpo p { margin: 0 0 14px 0; }
        .destaque { font-weight: bold; color: #111827; }
        table.dados { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 10.5pt; }
        table.dados td { border: 1px solid #d1d5db; padding: 6px 9px; }
        table.dados td.label { background: #f3f4f6; font-weight: bold; width: 38%; }
        .fecho { margin-top: 36px; text-align: right; font-size: 11pt; }
        .assinaturas { margin-top: 60px; width: 100%; display: table; }
        .assinaturas .sig { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 16px; }
        .assinaturas .line { border-top: 1px solid #374151; margin: 0 auto 4px auto; width: 80%; padding-top: 4px; }
        .assinaturas .nome { font-size: 10pt; font-weight: bold; }
        .assinaturas .cargo { font-size: 9pt; color: #4b5563; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-cell">
            <?php if ($logoData !== ''): ?>
                <img src="<?= $esc($logoData) ?>" alt="Logo">
            <?php endif; ?>
        </div>
        <div class="title-cell">
            <p class="escola"><?= $esc($nomeUnidade) ?></p>
            <?php if ($linhaEndereco !== ''): ?><p class="meta"><?= $esc($linhaEndereco) ?></p><?php endif; ?>
            <?php if ($linhaDocs !== ''): ?><p class="meta"><?= $esc($linhaDocs) ?></p><?php endif; ?>
        </div>
    </div>
    <?php
    $rotuloNum = in_array((string) ($tipo ?? ''), \App\Services\DeclarationService::TIPOS, true)
        ? 'Declaração'
        : 'Documento';
    ?>
    <?php if (!empty($numero)): ?>
        <div class="doc-num"><?= $esc($rotuloNum) ?> nº <?= (int) $numero ?>/<?= (int) ($ano ?? date('Y')) ?></div>
    <?php else: ?>
        <div class="doc-num">&nbsp;</div>
    <?php endif; ?>

    <h1 class="doc-title"><?= $esc($titulo ?? 'Declaração') ?></h1>

    <div class="corpo">
