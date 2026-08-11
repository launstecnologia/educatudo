<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
$preview = isset($preview) && $preview;
?>
<div class="max-w-4xl mx-auto px-6 py-8">
    <?php if ($preview): ?>
    <div class="bg-amber-100 border border-amber-400 text-amber-900 rounded-lg p-4 mb-6 flex items-center justify-between">
        <span class="font-semibold">👁 Preview — como o aluno vê a prova</span>
        <a href="<?= URL ?>/professor/provas/editar/<?= (int)$prova['id'] ?>" class="text-amber-800 underline font-medium">Voltar para edição</a>
    </div>
    <?php endif; ?>

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($prova['titulo']) ?></h2>
        <p class="text-gray-600"><?= htmlspecialchars($prova['materia_nome'] ?? 'Sem matéria') ?></p>
    </div>

    <?php if (empty($questoes) || !is_array($questoes)): ?>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center text-gray-600">
        Nenhuma questão nesta prova.
    </div>
    <?php else: ?>
    <div class="space-y-6">
        <?php foreach ($questoes as $index => $questao): ?>
        <div class="questao-container bg-white rounded-xl shadow-lg p-6 border-2 border-gray-200">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Questão <?= $index + 1 ?> de <?= count($questoes) ?>
                        <span class="text-sm font-normal text-gray-600">(<?= number_format($questao['valor'], 2, ',', '.') ?> pontos)</span>
                    </h3>
                </div>
                <span class="text-xs text-gray-500">
                    <?php
                    $tipos = ['multipla_escolha' => 'Múltipla Escolha', 'verdadeiro_falso' => 'Verdadeiro/Falso', 'dissertativa' => 'Dissertativa'];
                    echo $tipos[$questao['tipo']] ?? $questao['tipo'];
                    ?>
                </span>
            </div>
            <div class="text-gray-700 mb-4 text-lg"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>
            <?php if (!empty($questao['imagem_url'])): ?>
            <div class="mb-4">
                <img src="<?= htmlspecialchars($questao['imagem_url']) ?>" alt="Imagem da questão" class="max-w-full rounded-lg border border-gray-300">
            </div>
            <?php endif; ?>
            <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
            <div class="space-y-3">
                <?php foreach ($questao['alternativas'] as $alt): ?>
                <div class="flex items-start p-4 border-2 border-gray-200 rounded-lg bg-gray-50">
                    <span class="mr-3 w-5 h-5 rounded-full border-2 border-gray-400 flex-shrink-0 mt-0.5"></span>
                    <span class="text-gray-700 text-base"><?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($questao['tipo'] === 'dissertativa'): ?>
            <div class="p-4 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-500 text-sm">
                [Campo de resposta dissertativa — o aluno verá uma caixa de texto aqui]
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mt-8 pt-6 border-t">
        <a href="<?= URL ?>/professor/provas/editar/<?= (int)$prova['id'] ?>" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 font-semibold">
            ← Voltar para edição
        </a>
    </div>
</div>
