<?php
/** PDF da carteirinha. Variáveis: $cartoes, $escola */
$cartoes = is_array($cartoes ?? null) ? $cartoes : [];
$escola = (string) ($escola ?? 'EducaTudo');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rotulo = [
    'valida' => 'Válida',
    'vencida' => 'Vencida',
    'cancelada' => 'Cancelada',
    'inativa' => 'Aluno inativo',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #111827; }
.cartao { width: 360px; border: 1px solid #d1d5db; margin: 0 0 16px 0; page-break-inside: avoid; }
.faixa { color: #fff; padding: 10px 12px; font-size: 13px; }
.kicker { font-size: 9px; letter-spacing: 0.5px; }
.nome { font-size: 14px; font-weight: bold; }
.meta { font-size: 11px; color: #374151; margin-top: 4px; }
.foto { width: 72px; height: 90px; }
.qr { width: 84px; height: 84px; }
.codigo { font-size: 9px; color: #6b7280; text-align: center; }
</style>
</head>
<body>
<?php foreach ($cartoes as $cartao): ?>
    <?php
    $situacao = (string) ($cartao['situacao'] ?? '');
    $cor = $situacao === 'valida' ? '#0f766e' : '#b45309';
    $foto = (string) ($cartao['foto'] ?? '');
    $logo = (string) ($cartao['logo'] ?? '');
    $qr = (string) ($cartao['qr_pdf'] ?? '');
    ?>
    <table class="cartao" cellspacing="0" cellpadding="0">
        <tr>
            <td class="faixa" colspan="3" style="background:<?= $e($cor) ?>">
                <?php if ($logo !== ''): ?>
                    <img src="<?= $e($logo) ?>" alt="" style="height:22px;width:auto;background:#fff;padding:2px">
                <?php endif; ?>
                <div class="kicker">CARTEIRINHA ESCOLAR</div>
                <div><?= $e($cartao['escola'] ?? $escola) ?></div>
            </td>
        </tr>
        <tr>
            <td style="padding:10px;width:84px;vertical-align:top">
                <?php if ($foto !== ''): ?>
                    <img class="foto" src="<?= $e($foto) ?>" alt="">
                <?php else: ?>
                    <div class="foto" style="background:#f3f4f6;font-size:10px;color:#6b7280;text-align:center;padding-top:36px">Sem foto</div>
                <?php endif; ?>
            </td>
            <td style="padding:10px 6px;vertical-align:top">
                <div class="nome"><?= $e($cartao['nome'] ?? '') ?></div>
                <div class="meta">RA <?= $e(($cartao['ra'] ?? '') !== '' ? $cartao['ra'] : '—') ?></div>
                <div class="meta"><?= $e(($cartao['turma'] ?? '') !== '' ? $cartao['turma'] : 'Sem turma') ?></div>
                <div class="meta">Válida até <?= $e($cartao['validade'] ?? '') ?></div>
                <div class="meta" style="color:<?= $e($cor) ?>;font-weight:bold"><?= $e($rotulo[$situacao] ?? '') ?></div>
            </td>
            <td style="padding:10px;width:96px;vertical-align:top;text-align:center">
                <?php if ($qr !== ''): ?>
                    <img class="qr" src="<?= $e($qr) ?>" alt="">
                <?php endif; ?>
                <div class="codigo"><?= $e($cartao['codigo'] ?? '') ?></div>
            </td>
        </tr>
    </table>
<?php endforeach; ?>
</body>
</html>
