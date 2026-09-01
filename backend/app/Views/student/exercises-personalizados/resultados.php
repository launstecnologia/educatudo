<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Resultados dos exercícios</h1>
        <p class="text-lg text-gray-600 break-words line-clamp-2" title="<?= htmlspecialchars($sessao['lista_titulo'] ?? '') ?>">
            <?= htmlspecialchars($sessao['lista_titulo'] ?? '') ?>
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-3xl font-bold text-gray-900"><?= (int) $total ?></div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600"><?= (int) $corretas ?></div>
                <div class="text-sm text-gray-600">Corretas</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-red-600"><?= (int) $erradas ?></div>
                <div class="text-sm text-gray-600">Erradas</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold <?= $percentual >= 70 ? 'text-green-600' : ($percentual >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= htmlspecialchars((string) $percentual) ?>%
                </div>
                <div class="text-sm text-gray-600">Aproveitamento</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border-l-4 <?= $percentual >= 70 ? 'border-green-500' : ($percentual >= 50 ? 'border-yellow-500' : 'border-red-500') ?>">
        <div class="flex items-start">
            <?php if ($percentual >= 70): ?>
                <i class="fa-solid fa-circle-check text-3xl text-green-500 mt-0.5"></i>
                <div class="ml-4">
                    <h3 class="text-xl font-bold text-green-800">Excelente desempenho</h3>
                    <p class="text-gray-700">Você alcançou <?= htmlspecialchars((string) $percentual) ?>% de acertos. Parabéns pelo resultado.</p>
                </div>
            <?php elseif ($percentual >= 50): ?>
                <i class="fa-solid fa-circle-exclamation text-3xl text-yellow-500 mt-0.5"></i>
                <div class="ml-4">
                    <h3 class="text-xl font-bold text-yellow-800">Bom desempenho</h3>
                    <p class="text-gray-700">Você acertou <?= htmlspecialchars((string) $percentual) ?>% das questões. Continue estudando para melhorar.</p>
                </div>
            <?php else: ?>
                <i class="fa-solid fa-circle-xmark text-3xl text-red-500 mt-0.5"></i>
                <div class="ml-4">
                    <h3 class="text-xl font-bold text-red-800">Continue estudando</h3>
                    <p class="text-gray-700">Você acertou <?= htmlspecialchars((string) $percentual) ?>% das questões. Revise o conteúdo e tente novamente.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Detalhes das questões</h2>
        <div class="space-y-4">
            <?php foreach ($questoes as $index => $questao): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 border-l-4 <?= (int) ($questao['is_correct'] ?? 0) === 1 ? 'border-l-green-500' : 'border-l-red-500' ?>">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Questão <?= $index + 1 ?></h3>
                        <div class="flex items-center flex-wrap gap-2 flex-shrink-0">
                            <?php if ((int) ($questao['is_correct'] ?? 0) === 1): ?>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                    <i class="fa-solid fa-check mr-1"></i>Correta
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                                    <i class="fa-solid fa-xmark mr-1"></i>Incorreta
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($questao['nivel_dificuldade'])): ?>
                                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
                                    <?= htmlspecialchars($questao['nivel_dificuldade']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="text-gray-700 mb-4 break-words"><?= nl2br(htmlspecialchars($questao['pergunta'])) ?></p>

                    <?php include __DIR__ . '/_questao_revisao.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-wrap justify-between items-center gap-3">
        <a href="<?= URL ?>/exercicios-personalizados"
           class="inline-flex items-center px-6 py-3 border border-gray-300 bg-white text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-semibold">
            <i class="fa-solid fa-arrow-left mr-2"></i>
            Voltar para exercícios
        </a>
        <a href="<?= URL ?>/exercicios-personalizados/minhas-listas"
           class="inline-flex items-center px-6 py-3 btn-ai-primary rounded-xl font-semibold">
            Ver minhas listas
        </a>
    </div>
</div>
