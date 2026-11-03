<?php
/** Página pública de validação da carteirinha. Variáveis: $info, $escola */
$info = is_array($info ?? null) ? $info : [];
$escola = (string) ($escola ?? 'EducaTudo');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uso = !empty($info['uso']);
$encontrado = !empty($info['encontrado']);
$valido = !empty($info['valido']);
$motivo = (string) ($info['motivo'] ?? '');
if (!$uso && $motivo === 'indisponivel') {
    $titulo = 'Validação indisponível';
    $selo = 'Indisponível';
    $cor = '#991b1b';
    $fundo = '#fef2f2';
} elseif (!$uso) {
    $titulo = 'Esta escola não usa carteirinha';
    $selo = 'Desligada';
    $cor = '#92400e';
    $fundo = '#fffbeb';
} elseif (!$encontrado) {
    $titulo = 'Carteirinha não encontrada';
    $selo = 'Não encontrada';
    $cor = '#991b1b';
    $fundo = '#fef2f2';
} elseif ($valido) {
    $titulo = 'Carteirinha válida';
    $selo = 'Válida';
    $cor = '#065f46';
    $fundo = '#ecfdf5';
} elseif ($motivo === 'vencida') {
    $titulo = 'Carteirinha vencida';
    $selo = 'Vencida';
    $cor = '#9a3412';
    $fundo = '#fff7ed';
} elseif ($motivo === 'inativa') {
    $titulo = 'Aluno inativo';
    $selo = 'Inativa';
    $cor = '#9a3412';
    $fundo = '#fff7ed';
} else {
    $titulo = 'Carteirinha cancelada';
    $selo = 'Cancelada';
    $cor = '#991b1b';
    $fundo = '#fef2f2';
}
$foto = (string) ($info['foto'] ?? '');
$logo = (string) ($info['logo'] ?? '');
$nomeEscola = (string) ($info['escola'] ?? $escola);
$hash = strtolower((string) ($info['hash'] ?? ''));
$hashGrupos = $hash !== '' ? trim(chunk_split(strtoupper($hash), 4, ' ')) : '';
$consulta = date('d/m/Y \à\s H:i');
$via = (int) ($info['versao'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Validação de carteirinha · <?= $e($nomeEscola) ?></title>
<?php include dirname(__DIR__, 4) . '/Views/layouts/components/estilos_plataforma.php'; ?>
<style>
body{margin:0;background:#e7edf3;color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif}
.valida{min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:20px 14px 32px;box-sizing:border-box}
.doc{width:100%;max-width:440px;background:#fff;border:1px solid #d6dee8;border-radius:18px;box-shadow:0 16px 40px rgba(15,23,42,.08);overflow:hidden}
.cabeca{padding:22px 22px 16px;text-align:center;border-bottom:1px solid #e8eef5}
.cabeca img{height:56px;width:auto;max-width:180px;object-fit:contain;margin:0 auto 10px;display:block}
.escola{font-size:17px;font-weight:700;letter-spacing:-.01em}
.kicker{margin-top:4px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#64748b}
.status{margin:16px 22px 0;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:12px}
.selo{width:46px;height:46px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;text-align:center;line-height:1.1;flex-shrink:0}
.status h1{margin:0;font-size:18px;line-height:1.2}
.status p{margin:2px 0 0;font-size:12px;opacity:.8}
.corpo{padding:18px 22px 8px}
.pessoa{display:flex;gap:14px;align-items:center}
.foto{width:72px;height:90px;border-radius:10px;background:#f1f5f9;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0}
.foto img{width:100%;height:100%;object-fit:cover}
.foto span{font-size:11px;color:#94a3b8}
.nome{font-size:16px;font-weight:700;line-height:1.25}
.turma{margin-top:4px;font-size:13px;color:#475569}
.ficha{margin-top:16px;border-top:1px solid #e8eef5}
.linha{display:flex;justify-content:space-between;gap:16px;padding:11px 0;border-bottom:1px solid #e8eef5;font-size:14px}
.linha dt{color:#64748b}
.linha dd{margin:0;font-weight:600;text-align:right}
.hash{margin-top:16px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:12px 14px}
.hash span{display:block;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.hash code{display:block;margin-top:6px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;line-height:1.45;letter-spacing:.04em;word-break:break-word;color:#0f172a}
.aviso{padding:8px 0 4px;text-align:center;color:#475569;font-size:14px;line-height:1.45}
.rodape{padding:8px 22px 18px;text-align:center}
.rodape p{margin:0;font-size:11px;line-height:1.45;color:#94a3b8}
.rodape strong{color:#64748b;font-weight:600}
</style>
</head>
<body>
<div class="valida">
    <article class="doc">
        <header class="cabeca">
            <?php if ($logo !== ''): ?>
                <img src="<?= $e($logo) ?>" alt="">
            <?php endif; ?>
            <div class="escola"><?= $e($nomeEscola) ?></div>
            <div class="kicker">Validação de carteirinha escolar</div>
        </header>
        <div class="status" style="background:<?= $e($fundo) ?>;color:<?= $e($cor) ?>">
            <div class="selo"><?= $e($selo) ?></div>
            <div>
                <h1><?= $e($titulo) ?></h1>
                <p>Situação conferida neste momento</p>
            </div>
        </div>
        <div class="corpo">
            <?php if ($encontrado): ?>
                <div class="pessoa">
                    <div class="foto">
                        <?php if ($foto !== ''): ?>
                            <img src="<?= $e($foto) ?>" alt="">
                        <?php else: ?>
                            <span>Sem foto</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="nome"><?= $e($info['aluno_nome'] ?? '') ?></div>
                        <div class="turma"><?= $e(($info['turma'] ?? '') !== '' ? $info['turma'] : 'Sem turma') ?></div>
                    </div>
                </div>
                <dl class="ficha">
                    <div class="linha">
                        <dt>RA</dt>
                        <dd><?= $e(($info['ra'] ?? '') !== '' ? $info['ra'] : '—') ?></dd>
                    </div>
                    <div class="linha">
                        <dt>Válida até</dt>
                        <dd><?= $e(($info['validade'] ?? '') !== '' ? $info['validade'] : '—') ?></dd>
                    </div>
                    <?php if (($info['emitida_em'] ?? '') !== ''): ?>
                    <div class="linha">
                        <dt>Emitida em</dt>
                        <dd><?= $e($info['emitida_em']) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($via > 0): ?>
                    <div class="linha">
                        <dt>Via</dt>
                        <dd><?= $via ?>ª</dd>
                    </div>
                    <?php endif; ?>
                    <div class="linha">
                        <dt>Código</dt>
                        <dd><?= $e($info['codigo'] ?? '') ?></dd>
                    </div>
                </dl>
                <?php if ($hashGrupos !== ''): ?>
                <div class="hash">
                    <span>Hash de autenticação</span>
                    <code><?= $e($hashGrupos) ?></code>
                </div>
                <?php endif; ?>
            <?php elseif (!$uso): ?>
                <p class="aviso">A validação online só responde enquanto a escola mantém a carteirinha ligada.</p>
            <?php else: ?>
                <p class="aviso">Não localizamos uma carteirinha com este código. O documento pode ter sido substituído ou o QR não pertence a esta escola.</p>
            <?php endif; ?>
        </div>
        <footer class="rodape">
            <p>Consulta registrada em <strong><?= $e($consulta) ?></strong>.</p>
            <p>Este comprovante confirma a autenticidade da carteirinha emitida por <?= $e($nomeEscola) ?>. Não exibe dados além da identificação do aluno.</p>
        </footer>
    </article>
</div>
</body>
</html>
