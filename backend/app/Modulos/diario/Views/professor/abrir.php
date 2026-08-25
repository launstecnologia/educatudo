<?php
$turmaId = (int) ($info['turma_id'] ?? 0);
$materiaId = (int) ($info['materia_id'] ?? 0);
$abas = [
    'resumo' => 'Resumo',
    'aulas' => 'Aulas',
    'frequencia' => 'Frequência',
    'planejamento' => 'Planejamento',
    'notas' => 'Avaliações e Notas',
    'fechamento' => 'Fechamento',
];
$baseQuery = 'turma_id=' . $turmaId . '&materia_id=' . $materiaId . '&inicio=' . urlencode($inicio) . '&fim=' . urlencode($fim);
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <a href="<?= URL ?>/professor/diarios" class="text-purple-700 hover:underline text-sm">← Voltar aos diários</a>
        <h1 class="text-3xl font-bold text-gray-900 mt-2"><?= htmlspecialchars((string) ($info['materia_nome'] ?? '')) ?> — <?= htmlspecialchars((string) ($info['turma_nome'] ?? '')) ?></h1>
        <p class="text-gray-600 mt-1">
            Professor(a): <?= htmlspecialchars((string) ($info['professor_nome'] ?? '')) ?>
            · Ano Letivo: <?= (int) $ano_letivo ?>
            · Período: <?= date('d/m/Y', strtotime($inicio)) ?> a <?= date('d/m/Y', strtotime($fim)) ?>
        </p>
    </div>

    <div class="border-b border-gray-200">
        <nav class="flex flex-wrap gap-1 -mb-px">
            <?php foreach ($abas as $chave => $label): ?>
                <a href="<?= URL ?>/professor/diarios/abrir?<?= $baseQuery ?>&aba=<?= $chave ?>"
                   class="px-4 py-2.5 text-sm font-semibold border-b-2 <?= $aba === $chave ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php
    $abaValida = array_key_exists($aba, $abas) ? $aba : 'resumo';
    $partial = __DIR__ . '/_aba_' . $abaValida . '.php';
    if (is_file($partial)) {
        require $partial;
    }
    ?>
</div>
