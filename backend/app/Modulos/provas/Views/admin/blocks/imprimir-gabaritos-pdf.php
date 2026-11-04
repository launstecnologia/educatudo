<?php
/**
 * PDF do bloco impresso: questões de cada matéria e um gabarito por página.
 *
 * @var array<string, mixed> $bloco
 * @var list<array{prova: array<string, mixed>}> $pacotes
 * @var list<array<string, mixed>> $folhas
 */
$bloco = $bloco ?? [];
$pacotes = $pacotes ?? [];
$folhas = $folhas ?? [];
$gabaritoConjunto = !empty($gabarito_conjunto);
$materias = [];
foreach ($pacotes as $pacote) {
    $nome = trim((string) ($pacote['prova']['materia_nome'] ?? ''));
    if ($nome !== '') {
        $materias[] = $nome;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        h2 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #444; font-size: 11px; margin-bottom: 10px; }
        .capa p { margin: 4px 0; }
        .materia { page-break-before: always; }
        .questao { margin: 0 0 12px; page-break-inside: avoid; }
        .questao-num { font-weight: bold; margin-bottom: 3px; }
        .alternativa { margin: 0 0 2px 12px; }
        .gabarito { page-break-before: always; }
        .gabarito h2 { font-size: 12px; margin: 0 0 4px; }
        .gabarito img { width: 180mm; height: auto; }
    </style>
</head>
<body>
    <div class="capa">
        <h1><?= htmlspecialchars((string) ($bloco['titulo'] ?? 'Prova impressa')) ?></h1>
        <p class="meta">
            Data: <?= !empty($bloco['data_prova']) ? date('d/m/Y', strtotime((string) $bloco['data_prova'])) : '—' ?>
            — <?= !empty($bloco['hora_inicio']) ? date('H:i', strtotime((string) $bloco['hora_inicio'])) : '—' ?>
            às <?= !empty($bloco['hora_fim']) ? date('H:i', strtotime((string) $bloco['hora_fim'])) : '—' ?>
        </p>
        <p>Matérias neste bloco: <?= $materias === [] ? '—' : htmlspecialchars(implode(', ', $materias)) ?>.</p>
        <?php if ($gabaritoConjunto): ?>
        <p>Cada aluno faz todas as matérias e marca todas no mesmo gabarito, com o QR Code do aluno.</p>
        <?php else: ?>
        <p>Gabarito só desta matéria, com o QR Code do aluno.</p>
        <?php endif; ?>
    </div>

    <?php foreach ($pacotes as $pacote): ?>
    <?php $prova = $pacote['prova']; ?>
    <div class="materia">
        <h2><?= htmlspecialchars((string) ($prova['materia_nome'] ?? $prova['titulo'] ?? 'Matéria')) ?></h2>
        <p class="meta">
            <?= htmlspecialchars((string) ($prova['titulo'] ?? '')) ?>
            — <?= htmlspecialchars((string) ($prova['professor_nome'] ?? '')) ?>
        </p>
        <?php if (empty($prova['questoes'])): ?>
        <p>Nenhuma questão nesta matéria.</p>
        <?php else: ?>
            <?php foreach ($prova['questoes'] as $i => $questao): ?>
            <div class="questao">
                <div class="questao-num">Questão <?= (int) $i + 1 ?> — <?= number_format((float) ($questao['valor'] ?? 0), 1, ',', '') ?> pt</div>
                <div><?= htmlspecialchars(trim(strip_tags((string) ($questao['enunciado'] ?? '')))) ?></div>
                <?php if (($questao['tipo'] ?? '') === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                    <?php $letras = ['A', 'B', 'C', 'D', 'E', 'F']; ?>
                    <?php foreach ($questao['alternativas'] as $indice => $alternativa): ?>
                    <div class="alternativa"><?= htmlspecialchars(($letras[$indice] ?? '') . ') ' . trim(strip_tags((string) ($alternativa['texto'] ?? '')))) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php foreach ($folhas as $folha): ?>
    <div class="gabarito">
        <h2>
            Gabarito — <?= htmlspecialchars((string) ($folha['aluno_nome'] ?? '')) ?>
            <?php if (!$gabaritoConjunto && !empty($folha['materia_nome'])): ?>
            — <?= htmlspecialchars((string) $folha['materia_nome']) ?>
            <?php endif; ?>
            <?php if (!empty($folha['turma_nome'])): ?>
            — Turma <?= htmlspecialchars((string) $folha['turma_nome']) ?>
            <?php endif; ?>
        </h2>
        <?php if (!empty($folha['imagem'])): ?>
        <img src="<?= $folha['imagem'] ?>" alt="Gabarito">
        <?php else: ?>
        <p><?= htmlspecialchars((string) (($folha['erro'] ?? '') !== '' ? $folha['erro'] : 'Folha não gerada.')) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</body>
</html>
