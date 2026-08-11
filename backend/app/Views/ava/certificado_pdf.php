<?php
/** Template do certificado (dompdf, A4 paisagem). Variáveis: $cert, $escola, $logo_data, $validation_url, $emitido_em */
$cert = $cert ?? [];
$escola = (string) ($escola ?? 'EducaTudo');
$logo = (string) ($logo_data ?? '');
$validationUrl = (string) ($validation_url ?? '');
$emitido = (string) ($emitido_em ?? date('d/m/Y'));
$alunoNome = (string) ($cert['aluno_nome'] ?? 'Aluno(a)');
$titulo = (string) ($cert['titulo'] ?? 'Disciplina');
$carga = (int) ($cert['carga_horaria'] ?? 0);
$codigo = (string) ($cert['codigo'] ?? '');
$nota = isset($cert['nota_final']) && $cert['nota_final'] !== null ? number_format((float) $cert['nota_final'], 1, ',', '') : '';
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0; }
    body { font-family: "DejaVu Sans", sans-serif; margin: 0; color: #1f2937; }
    .sheet { width: 100%; box-sizing: border-box; padding: 28px; }
    .frame { border: 6px solid #166534; border-radius: 10px; padding: 26px 40px; }
    .inner { border: 1px solid #bbf7d0; border-radius: 6px; padding: 26px 36px; text-align: center; }
    .logo { height: 56px; margin-bottom: 6px; }
    .escola { font-size: 14px; letter-spacing: 1px; color: #166534; text-transform: uppercase; font-weight: bold; }
    .titulo { font-size: 40px; font-weight: bold; color: #14532d; margin: 14px 0 4px; letter-spacing: 2px; }
    .sub { font-size: 13px; color: #6b7280; margin-bottom: 22px; }
    .texto { font-size: 15px; color: #374151; line-height: 1.7; margin: 0 auto; max-width: 90%; }
    .nome { font-size: 28px; font-weight: bold; color: #111827; margin: 10px 0; border-bottom: 1px solid #d1d5db; display: inline-block; padding: 0 24px 6px; }
    .disc { font-weight: bold; color: #14532d; }
    .rodape { margin-top: 30px; font-size: 11px; color: #6b7280; }
    .codigo { font-family: "DejaVu Sans Mono", monospace; font-size: 12px; color: #166534; font-weight: bold; }
    .assinatura { margin-top: 26px; }
    .linha-ass { width: 240px; border-top: 1px solid #9ca3af; margin: 0 auto; padding-top: 4px; font-size: 12px; color: #4b5563; }
</style>
</head>
<body>
<div class="sheet">
    <div class="frame">
        <div class="inner">
            <?php if ($logo !== ''): ?><img src="<?= $e($logo) ?>" class="logo" alt="logo"><br><?php endif; ?>
            <div class="escola"><?= $e($escola) ?></div>
            <div class="titulo">CERTIFICADO</div>
            <div class="sub">de conclusão</div>

            <div class="texto">
                Certificamos que
                <div class="nome"><?= $e($alunoNome) ?></div>
                concluiu com aproveitamento a disciplina
                <span class="disc"><?= $e($titulo) ?></span><?php if ($carga > 0): ?>,
                com carga horária de <strong><?= $carga ?> hora(s)</strong><?php endif; ?><?php if ($nota !== ''): ?>,
                obtendo nota final <strong><?= $e($nota) ?></strong><?php endif; ?>.
            </div>

            <div class="assinatura">
                <div class="linha-ass"><?= $e($escola) ?></div>
            </div>

            <div class="rodape">
                Emitido em <?= $e($emitido) ?> · Código de validação: <span class="codigo"><?= $e($codigo) ?></span><br>
                <?php if ($validationUrl !== ''): ?>Valide a autenticidade em: <?= $e($validationUrl) ?><?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
