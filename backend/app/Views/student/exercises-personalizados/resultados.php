<?php
$formatarExplicacaoIA = function (?string $texto): string {
    $texto = trim((string)$texto);
    if ($texto === '') {
        return '<p class="text-sm text-gray-600">Sem explicação disponível.</p>';
    }

    // Normaliza quebras e escapa HTML primeiro.
    $safe = htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $texto), ENT_QUOTES, 'UTF-8');
    $safe = nl2br($safe);

    // Destaca títulos comuns do prompt.
    $safe = preg_replace(
        '/\*\*Alternativa\s+([A-E])\)\*\*/i',
        '<span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold text-xs mr-2">Alternativa $1</span>',
        $safe
    );
    $safe = preg_replace(
        '/\*\*(CORRETA|INCORRETA)\*\*/i',
        '<span class="font-semibold text-slate-900">$1</span>',
        $safe
    );
    $safe = preg_replace(
        '/\*\*Gabarito:\s*([A-E])\*\*/i',
        '<span class="inline-flex items-center px-2.5 py-1 rounded bg-emerald-100 text-emerald-800 font-semibold text-xs">Gabarito: $1</span>',
        $safe
    );
    $safe = str_replace('**', '', $safe);

    // Quebra visual melhor entre blocos de explicação.
    $blocos = preg_split('/(?:<br\s*\/?>\s*){2,}/i', $safe);
    $blocos = array_values(array_filter(array_map('trim', $blocos), static function ($item) {
        return $item !== '';
    }));

    if (empty($blocos)) {
        return '<p class="text-sm text-gray-700 leading-relaxed">' . $safe . '</p>';
    }

    $html = '';
    foreach ($blocos as $bloco) {
        $classe = (stripos($bloco, 'Gabarito:') !== false)
            ? 'text-sm pt-2 border-t border-emerald-200'
            : 'text-sm text-gray-700 leading-relaxed';
        $html .= '<p class="' . $classe . '">' . $bloco . '</p>';
    }

    return $html;
};
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Resultados dos Exercícios</h1>
        <p class="text-lg text-gray-600"><?= htmlspecialchars($sessao['lista_titulo']) ?></p>
    </div>

    <!-- Card de Estatísticas -->
    <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl shadow-2xl p-8 mb-8 text-white">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-4xl font-bold"><?= $total ?></div>
                <div class="text-sm opacity-90">Total</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-bold"><?= $corretas ?></div>
                <div class="text-sm opacity-90">Corretas</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-bold"><?= $erradas ?></div>
                <div class="text-sm opacity-90">Erradas</div>
            </div>
            <div class="text-center">
                <div class="text-4xl font-bold"><?= $percentual ?>%</div>
                <div class="text-sm opacity-90">Aproveitamento</div>
            </div>
        </div>
    </div>

    <!-- Mensagem de Feedback -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border-l-4 <?= $percentual >= 70 ? 'border-green-500' : ($percentual >= 50 ? 'border-yellow-500' : 'border-red-500') ?>">
        <div class="flex items-center">
            <?php if ($percentual >= 70): ?>
                <div class="flex-shrink-0">
                    <svg class="w-12 h-12 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-xl font-bold text-green-800">Excelente Desempenho!</h3>
                    <p class="text-gray-700">Você alcançou <?= $percentual ?>% de acertos. Parabéns pelo ótimo resultado!</p>
                </div>
            <?php elseif ($percentual >= 50): ?>
                <div class="flex-shrink-0">
                    <svg class="w-12 h-12 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-xl font-bold text-yellow-800">Bom Desempenho</h3>
                    <p class="text-gray-700">Você acertou <?= $percentual ?>% das questões. Continue estudando para melhorar!</p>
                </div>
            <?php else: ?>
                <div class="flex-shrink-0">
                    <svg class="w-12 h-12 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-xl font-bold text-red-800">Continue Estudando</h3>
                    <p class="text-gray-700">Você acertou <?= $percentual ?>% das questões. Revise o conteúdo e tente novamente!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detalhes das Questões -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Detalhes das Questões</h2>
        <div class="space-y-4">
            <?php foreach ($questoes as $index => $questao): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 <?= $questao['is_correct'] == 1 ? 'border-green-500' : 'border-red-500' ?>">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Questão <?= $index + 1 ?></h3>
                        <div class="flex items-center space-x-2">
                            <?php if ($questao['is_correct'] == 1): ?>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                    ✓ Correto
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                                    ✗ Incorreto
                                </span>
                            <?php endif; ?>
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">
                                <?= htmlspecialchars($questao['nivel_dificuldade']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-700 mb-4"><?= nl2br(htmlspecialchars($questao['pergunta'])) ?></p>
                    
                    <div class="space-y-2 mb-4">
                        <?php 
                        $alternativas = [
                            'A' => $questao['alternativa_a'],
                            'B' => $questao['alternativa_b'],
                            'C' => $questao['alternativa_c'],
                            'D' => $questao['alternativa_d']
                        ];
                        
                        if (!empty($questao['alternativa_e'])) {
                            $alternativas['E'] = $questao['alternativa_e'];
                        }
                        
                        foreach ($alternativas as $letra => $texto):
                            $classe = '';
                            if ($letra == $questao['resposta_correta']) {
                                $classe = 'border-green-500 bg-green-50';
                            } elseif ($letra == $questao['resposta_escolhida'] && $questao['is_correct'] == 0) {
                                $classe = 'border-red-500 bg-red-50';
                            } else {
                                $classe = 'border-gray-200';
                            }
                        ?>
                            <div class="p-3 border-2 rounded-lg <?= $classe ?>">
                                <span class="font-semibold"><?= $letra ?>.</span>
                                <span class="ml-2"><?= nl2br(htmlspecialchars($texto)) ?></span>
                                <?php if ($letra == $questao['resposta_correta']): ?>
                                    <span class="ml-2 text-green-700 font-semibold">✓ Resposta Correta</span>
                                <?php elseif ($letra == $questao['resposta_escolhida'] && $questao['is_correct'] == 0): ?>
                                    <span class="ml-2 text-red-700 font-semibold">✗ Sua Resposta</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
                        <p class="text-sm font-semibold text-slate-900 mb-1">💡 Explicação:</p>
                        <div class="space-y-2">
                            <?= $formatarExplicacaoIA($questao['explicacao'] ?? '') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Botões -->
    <div class="flex justify-between items-center">
        <a href="<?= URL ?>/exercicios-personalizados" 
           class="px-6 py-3 bg-gray-500 text-white rounded-xl hover:bg-gray-600 transition-colors font-semibold">
            ← Voltar para Exercícios
        </a>
        <a href="<?= URL ?>/exercicios-personalizados/minhas-listas" 
           class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition-colors font-semibold">
            Ver Minhas Listas
        </a>
    </div>
</div>

