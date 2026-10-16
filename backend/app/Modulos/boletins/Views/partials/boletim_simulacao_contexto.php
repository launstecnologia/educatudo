<?php
$simulacaoMensagem = trim((string) ($simulacao_mensagem ?? ''));
$simulacaoAvisos = is_array($simulacao_avisos ?? null) ? $simulacao_avisos : [];
$simulacaoRotulo = trim((string) ($simulacao_rotulo_periodo ?? ''));
$simulacaoConfiguracao = trim((string) ($simulacao_configuracao ?? ''));
$simulacaoUrlVigente = trim((string) ($simulacao_url_vigente ?? ''));
$simulacaoTemVigente = !empty($simulacao_tem_vigente);
$simulacaoAlunoNome = trim((string) ($simulacao['aluno']['nome'] ?? ''));
$simulacaoTurmaNome = trim((string) ($simulacao['aluno']['turma_nome'] ?? ''));
$simulacaoModeloNome = trim((string) ($boletim['nome'] ?? $modelo_nome ?? ''));
$simulacaoAno = (int) ($regra['ano_letivo'] ?? $boletim['ano_letivo'] ?? 0);
$simulacaoFormula = trim((string) ($regra['formula_final'] ?? ''));
$mostrarContexto = is_array($simulacao ?? null) || $simulacaoMensagem !== '' || $simulacaoAvisos !== [];
if (!$mostrarContexto) {
    return;
}
?>
<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 space-y-2">
    <p class="font-semibold">Simulação com as notas atuais. O boletim oficial não foi alterado.</p>
    <?php if ($simulacaoMensagem !== ''): ?>
        <p><?= htmlspecialchars($simulacaoMensagem, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (is_array($simulacao ?? null)): ?>
        <p>
            <?php if ($simulacaoAlunoNome !== ''): ?>
                Aluno: <strong><?= htmlspecialchars($simulacaoAlunoNome, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php endif; ?>
            <?php if ($simulacaoTurmaNome !== ''): ?>
                · Turma: <strong><?= htmlspecialchars($simulacaoTurmaNome, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php endif; ?>
            <?php if ($simulacaoModeloNome !== ''): ?>
                · Modelo: <strong><?= htmlspecialchars($simulacaoModeloNome, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php endif; ?>
            <?php if ($simulacaoAno >= 2000): ?>
                · Ano: <strong><?= $simulacaoAno ?></strong>
            <?php endif; ?>
            <?php if ($simulacaoRotulo !== ''): ?>
                · Período: <strong><?= htmlspecialchars($simulacaoRotulo, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php endif; ?>
        </p>
        <?php if ($simulacaoFormula !== ''): ?>
            <p>Fórmula: <strong><?= htmlspecialchars($simulacaoFormula, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <?php endif; ?>
        <?php if ($simulacaoConfiguracao !== ''): ?>
            <p><?= htmlspecialchars($simulacaoConfiguracao, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php foreach ($simulacaoAvisos as $aviso): ?>
        <?php $aviso = trim((string) $aviso); ?>
        <?php if ($aviso === '') { continue; } ?>
        <p><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endforeach; ?>
    <?php if ($simulacaoTemVigente && $simulacaoUrlVigente !== ''): ?>
        <p>
            <a class="font-semibold underline" href="<?= htmlspecialchars(URL . $simulacaoUrlVigente, ENT_QUOTES, 'UTF-8') ?>">Ver boletim vigente</a>
            · esta tela é “Simular com as notas atuais” e não substitui o resultado salvo.
        </p>
    <?php endif; ?>
</div>
