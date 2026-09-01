<?php
/**
 * Revisão de uma questão (histórico / resultados): todas as alternativas + explicação.
 * Espera $questao com alternativa_a..e, resposta_correta, resposta_escolhida, is_correct, explicacao.
 */
require_once __DIR__ . '/_formatar_explicacao.php';

$respostaCorreta = strtoupper(trim((string) ($questao['resposta_correta'] ?? '')));
$respostaEscolhida = strtoupper(trim((string) ($questao['resposta_escolhida'] ?? '')));
$acertou = (int) ($questao['is_correct'] ?? 0) === 1;

$alternativas = [];
foreach (['A', 'B', 'C', 'D', 'E'] as $letraAlt) {
    $textoAlt = trim((string) ($questao['alternativa_' . strtolower($letraAlt)] ?? ''));
    if ($textoAlt !== '') {
        $alternativas[$letraAlt] = $textoAlt;
    }
}
?>
<div class="space-y-2 mb-4">
    <?php foreach ($alternativas as $letra => $texto): ?>
        <?php
        $ehCorreta = ($letra === $respostaCorreta);
        $ehEscolhidaErrada = ($letra === $respostaEscolhida && !$acertou);
        if ($ehCorreta) {
            $classeBox = 'bg-green-50 border-green-200';
            $classeLetra = 'bg-green-500 text-white';
        } elseif ($ehEscolhidaErrada) {
            $classeBox = 'bg-red-50 border-red-200';
            $classeLetra = 'bg-red-500 text-white';
        } else {
            $classeBox = 'bg-gray-50 border-gray-200';
            $classeLetra = 'bg-gray-200 text-gray-700';
        }
        ?>
        <div class="flex items-start gap-3 p-3 rounded-lg border <?= $classeBox ?>">
            <span class="w-7 h-7 flex-shrink-0 rounded-full <?= $classeLetra ?> flex items-center justify-center text-xs font-bold">
                <?php if ($ehCorreta): ?>
                    <i class="fa-solid fa-check"></i>
                <?php elseif ($ehEscolhidaErrada): ?>
                    <i class="fa-solid fa-xmark"></i>
                <?php else: ?>
                    <?= htmlspecialchars($letra) ?>
                <?php endif; ?>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-gray-800 break-words">
                    <span class="font-semibold mr-1"><?= htmlspecialchars($letra) ?>)</span>
                    <?= nl2br(htmlspecialchars($texto)) ?>
                </p>
                <div class="mt-1 flex flex-wrap gap-2">
                    <?php if ($ehCorreta): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Resposta correta</span>
                    <?php endif; ?>
                    <?php if ($letra === $respostaEscolhida): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $acertou ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">Sua resposta</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($questao['explicacao'])): ?>
    <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
        <p class="text-sm font-semibold text-slate-900">
            <i class="fa-solid fa-lightbulb text-amber-500 mr-1"></i>
            Explicação
        </p>
        <div class="space-y-2">
            <?= formatarExplicacaoExercicioPersonalizado($questao['explicacao'] ?? '') ?>
        </div>
    </div>
<?php endif; ?>
