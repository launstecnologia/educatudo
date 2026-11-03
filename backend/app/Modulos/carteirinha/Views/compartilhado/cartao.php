<?php
/**
 * Cartão visual da carteirinha.
 * Variável: $cartao (array)
 */
$cartao = is_array($cartao ?? null) ? $cartao : [];
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$situacao = (string) ($cartao['situacao'] ?? '');
$rotulo = [
    'valida' => 'Válida',
    'vencida' => 'Vencida',
    'cancelada' => 'Cancelada',
    'inativa' => 'Aluno inativo',
][$situacao] ?? '';
$corStatus = $situacao === 'valida' ? '#0f766e' : '#b45309';
$corFaixa = trim((string) ($cartao['cor_barra'] ?? ''));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $corFaixa)) {
    $corFaixa = $corStatus;
}
$foto = (string) ($cartao['foto'] ?? '');
$logo = (string) ($cartao['logo'] ?? '');
$qr = (string) ($cartao['qr'] ?? '');
static $carteirinhaCss = false;
?>
<?php if (!$carteirinhaCss): $carteirinhaCss = true; ?>
<style>
.carteirinha-cartao{width:100%;max-width:420px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.12);border:1px solid #e5e7eb;font-family:ui-sans-serif,system-ui,sans-serif;color:#111827;box-sizing:border-box}
.carteirinha-faixa{color:#fff;padding:12px 14px;display:flex;align-items:center;gap:10px}
.carteirinha-faixa img{height:28px;width:auto;max-width:72px;object-fit:contain;background:#fff;border-radius:6px;padding:2px}
.carteirinha-faixa-texto{min-width:0}
.carteirinha-kicker{font-size:11px;letter-spacing:.04em;text-transform:uppercase;opacity:.85}
.carteirinha-escola{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.carteirinha-corpo{display:flex;gap:12px;padding:14px;align-items:flex-start}
.carteirinha-foto{width:84px;height:104px;border-radius:10px;background:#f3f4f6;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.carteirinha-foto img{width:100%;height:100%;object-fit:cover}
.carteirinha-foto span{font-size:11px;color:#6b7280;text-align:center;padding:4px}
.carteirinha-dados{min-width:0;flex:1}
.carteirinha-nome{font-size:16px;font-weight:700;line-height:1.25;overflow-wrap:anywhere}
.carteirinha-meta{margin-top:8px;font-size:12px;color:#4b5563}
.carteirinha-validade{margin-top:8px;font-size:12px;color:#111827}
.carteirinha-status{margin-top:6px;display:inline-block;font-size:11px;font-weight:700}
.carteirinha-qr{width:96px;flex-shrink:0;text-align:center}
.carteirinha-qr img{width:96px;height:96px}
.carteirinha-codigo{font-size:10px;letter-spacing:.04em;color:#6b7280;margin-top:2px}
@media screen and (max-width:480px){
  .carteirinha-corpo{flex-wrap:wrap}
  .carteirinha-qr{width:100%;display:flex;flex-direction:column;align-items:center;padding-top:8px}
  .carteirinha-qr img{width:min(220px,72vw);height:auto;aspect-ratio:1}
  .carteirinha-codigo{font-size:12px}
}
@media print{
  .carteirinha-cartao{width:340px;max-width:340px;box-shadow:none}
  .carteirinha-corpo{flex-wrap:nowrap}
  .carteirinha-qr{width:84px;display:block;padding-top:0}
  .carteirinha-qr img{width:84px;height:84px}
}
</style>
<?php endif; ?>
<article class="carteirinha-cartao">
    <div class="carteirinha-faixa" style="background:<?= $e($corFaixa) ?>">
        <?php if ($logo !== ''): ?>
            <img src="<?= $e($logo) ?>" alt="">
        <?php endif; ?>
        <div class="carteirinha-faixa-texto">
            <div class="carteirinha-kicker">Carteirinha escolar</div>
            <div class="carteirinha-escola"><?= $e($cartao['escola'] ?? '') ?></div>
        </div>
    </div>
    <div class="carteirinha-corpo">
        <div class="carteirinha-foto">
            <?php if ($foto !== ''): ?>
                <img src="<?= $e($foto) ?>" alt="">
            <?php else: ?>
                <span>Sem foto</span>
            <?php endif; ?>
        </div>
        <div class="carteirinha-dados">
            <div class="carteirinha-nome"><?= $e($cartao['nome'] ?? '') ?></div>
            <div class="carteirinha-meta">RA <?= $e(($cartao['ra'] ?? '') !== '' ? $cartao['ra'] : '—') ?></div>
            <div class="carteirinha-meta"><?= $e(($cartao['turma'] ?? '') !== '' ? $cartao['turma'] : 'Sem turma') ?></div>
            <div class="carteirinha-validade">Válida até <?= $e($cartao['validade'] ?? '') ?></div>
            <?php if ($rotulo !== ''): ?>
                <div class="carteirinha-status" style="color:<?= $e($corStatus) ?>"><?= $e($rotulo) ?></div>
            <?php endif; ?>
        </div>
        <div class="carteirinha-qr">
            <?php if ($qr !== ''): ?>
                <img src="<?= $e($qr) ?>" alt="QR Code de validação">
            <?php endif; ?>
            <div class="carteirinha-codigo"><?= $e($cartao['codigo'] ?? '') ?></div>
        </div>
    </div>
</article>
