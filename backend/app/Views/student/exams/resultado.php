<!-- Header Section -->
<div class="mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">
            Resultado da Prova: <?= htmlspecialchars($prova['titulo']) ?>
        </h2>
        <p class="text-gray-600">
            <?= htmlspecialchars($prova['materia_nome']) ?>
        </p>
    </div>
</div>

<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
$acertos = 0;
$erros = 0;
foreach ($questoes as $questao) {
    $r = $questao['resposta'] ?? null;
    if ($r !== null) {
        if (!empty($r['correta'])) {
            $acertos++;
        } else {
            $erros++;
        }
    }
}
$totalQuestoes = $acertos + $erros;
$percentual = $totalQuestoes > 0 ? ($acertos / $totalQuestoes) * 100 : 0;
?>
<!-- Resultado: acertos, erros e percentual -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Seu Resultado</h3>
        <div class="flex justify-center gap-8 mb-4">
            <div>
                <div class="text-sm text-gray-600">Acertos</div>
                <div class="text-3xl font-bold text-green-600"><?= $acertos ?></div>
            </div>
            <div>
                <div class="text-sm text-gray-600">Erros</div>
                <div class="text-3xl font-bold text-red-600"><?= $erros ?></div>
            </div>
        </div>
        <div class="mt-4">
            <?php $classPercentual = $percentual >= 70 ? 'bg-green-600' : ($percentual >= 50 ? 'bg-yellow-600' : 'bg-red-600'); ?>
            <span class="px-4 py-2 rounded-full text-white font-semibold <?= $classPercentual ?>">
                <?= number_format($percentual, 1) ?>%
            </span>
        </div>
    </div>
</div>

<!-- Detalhes da Realização -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Detalhes</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <p class="text-sm text-gray-600">Data de Início</p>
            <p class="text-lg font-semibold">
                <?= date('d/m/Y H:i', strtotime($realizacao['iniciado_em'])) ?>
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Data de Finalização</p>
            <p class="text-lg font-semibold">
                <?= $realizacao['finalizado_em'] ? date('d/m/Y H:i', strtotime($realizacao['finalizado_em'])) : '-' ?>
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-600">Tempo Gasto</p>
            <p class="text-lg font-semibold">
                <?= $realizacao['tempo_gasto'] ? $realizacao['tempo_gasto'] . ' minutos' : '-' ?>
            </p>
        </div>
    </div>
</div>

<!-- Questões e Respostas -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Questões e Respostas</h3>
    
    <div class="space-y-6">
        <?php foreach ($questoes as $index => $questao): ?>
            <?php $resposta = $questao['resposta'] ?? null; ?>
            <div class="border border-gray-200 rounded-xl p-5 <?= $resposta && $resposta['correta'] ? 'bg-green-50 border-green-300' : ($resposta && !$resposta['correta'] ? 'bg-red-50 border-red-300' : '') ?>">
                <div class="flex justify-between items-start mb-3">
                    <h4 class="font-semibold text-gray-900">
                        Questão <?= $index + 1 ?>
                        <span class="text-sm font-normal text-gray-600">(<?php
                            $tiposRes = ['multipla_escolha' => 'Múltipla Escolha', 'verdadeiro_falso' => 'Verdadeiro/Falso', 'dissertativa' => 'Dissertativa'];
                            echo $tiposRes[$questao['tipo']] ?? $questao['tipo'];
                        ?>)</span>
                        <span class="text-xs font-normal text-gray-500 ml-1"><?= number_format($questao['valor'], 2, ',', '.') ?> pt(s)</span>
                    </h4>
                    <?php if ($resposta): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold 
                            <?= $resposta['correta'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $resposta['correta'] ? '✓ Correta' : '✗ Incorreta' ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="text-gray-700 mb-4 text-lg prose prose-sm max-w-none"><?= isset($questao['enunciado']) ? LayoutHelper::renderEnunciadoProva($questao['enunciado']) : '' ?></div>
                
                <?php if (!empty($questao['imagem_url'])): ?>
                    <div class="mb-4">
                        <img src="<?= htmlspecialchars($questao['imagem_url']) ?>" alt="Imagem da questão" 
                             class="max-w-md rounded-lg border border-gray-300">
                    </div>
                <?php endif; ?>
                
                <?php if ($questao['tipo'] === 'multipla_escolha' && !empty($questao['alternativas'])): ?>
                    <?php $alternativaIdAluno = isset($resposta['alternativa_id']) ? (int)$resposta['alternativa_id'] : null; ?>
                    <div class="space-y-3">
                        <?php foreach ($questao['alternativas'] as $alt): ?>
                            <?php
                            $ehCorreta = !empty($alt['correta']);
                            $ehAssinaladaPeloAluno = ($alternativaIdAluno !== null && (int)$alt['id'] === $alternativaIdAluno);
                            $mostrarSuaResposta = $ehAssinaladaPeloAluno && $resposta && empty($resposta['correta']);
                            $classeCaixa = $ehCorreta ? 'bg-green-100 border-green-400' : ($mostrarSuaResposta ? 'bg-red-100 border-red-400' : 'border-gray-200 bg-white');
                            ?>
                            <div class="flex items-center p-4 border-2 rounded-xl <?= $classeCaixa ?>">
                                <?php if ($ehCorreta): ?>
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-500 flex items-center justify-center mr-3">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                    </span>
                                <?php elseif ($mostrarSuaResposta): ?>
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-red-500 flex items-center justify-center mr-3">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                    </span>
                                <?php else: ?>
                                    <span class="flex-shrink-0 w-6 h-6 rounded-full border-2 border-gray-300 mr-3"></span>
                                <?php endif; ?>
                                <span class="text-gray-700 flex-1 text-base"><?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?></span>
                                <?php if ($ehCorreta): ?>
                                    <span class="text-green-700 font-semibold ml-2 flex-shrink-0">✓ Correta</span>
                                <?php elseif ($mostrarSuaResposta): ?>
                                    <span class="text-red-700 font-semibold ml-2 flex-shrink-0">Sua resposta</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($resposta): ?>
                        <div class="mt-3 text-sm">
                            <span class="font-semibold">Pontuação obtida: </span>
                            <span class="<?= $resposta['correta'] ? 'text-green-600' : 'text-red-600' ?>">
                                <?= number_format($resposta['pontuacao'], 2, ',', '.') ?> pontos
                            </span>
                        </div>
                    <?php endif; ?>
                <?php elseif ($questao['tipo'] === 'dissertativa' && $resposta): ?>
                    <div class="bg-gray-50 rounded-lg p-4 mb-3">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Sua Resposta:</p>
                        <p class="text-gray-700"><?= nl2br(htmlspecialchars($resposta['resposta_texto'])) ?></p>
                    </div>
                    <div class="text-sm">
                        <span class="font-semibold">Pontuação obtida: </span>
                        <span class="<?= $resposta['correta'] ? 'text-green-600' : 'text-red-600' ?>">
                            <?= number_format($resposta['pontuacao'], 2, ',', '.') ?> pontos
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Botão Voltar -->
<div class="mt-6 text-center">
    <a href="<?= URL ?>/aluno/provas" 
       class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
        Voltar para Provas
    </a>
</div>

