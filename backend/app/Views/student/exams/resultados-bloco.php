<!-- Header Section -->
<div class="mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Resultados do Bloco: <?= htmlspecialchars($bloco['titulo']) ?></h1>
        <p class="text-gray-600 mt-2">Visualize seus resultados de todas as provas deste bloco.</p>
    </div>
</div>

<!-- Resumo Geral -->
<div class="bg-gradient-to-br from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Resumo Geral</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg p-4">
            <div class="text-sm text-gray-600">Total de Provas</div>
            <div class="text-2xl font-bold text-gray-900"><?= count($resultados) ?></div>
        </div>
        <div class="bg-white rounded-lg p-4">
            <div class="text-sm text-gray-600">Acertos</div>
            <div class="text-2xl font-bold text-green-600"><?= (int)($totalAcertos ?? 0) ?></div>
        </div>
        <div class="bg-white rounded-lg p-4">
            <div class="text-sm text-gray-600">Erros</div>
            <div class="text-2xl font-bold text-red-600"><?= (int)($totalErros ?? 0) ?></div>
        </div>
        <?php 
        $totalQuestoes = ($totalAcertos ?? 0) + ($totalErros ?? 0);
        $percentualGeral = $totalQuestoes > 0 ? (($totalAcertos ?? 0) / $totalQuestoes) * 100 : 0;
        ?>
        <div class="bg-white rounded-lg p-4">
            <div class="text-sm text-gray-600">Percentual</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= number_format($percentualGeral, 2, ',', '.') ?>%
            </div>
        </div>
    </div>
</div>

<!-- Resultados por Prova -->
<div class="space-y-4">
    <?php foreach ($resultados as $resultado): ?>
        <?php $prova = $resultado['prova']; ?>
        <?php $realizacao = $resultado['realizacao']; ?>
        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?= htmlspecialchars($prova['titulo']) ?>
                    </h3>
                    <p class="text-sm text-gray-600">
                        <?= htmlspecialchars($prova['materia_nome']) ?>
                    </p>
                </div>
                <?php 
                $acertos = (int)($resultado['acertos'] ?? 0);
                $erros = (int)($resultado['erros'] ?? 0);
                $totalQ = $acertos + $erros;
                $percentual = $totalQ > 0 ? ($acertos / $totalQ) * 100 : 0;
                ?>
                <div class="text-right">
                    <div class="text-sm text-gray-600">Acertos: <span class="font-bold text-green-600"><?= $acertos ?></span></div>
                    <div class="text-sm text-gray-600">Erros: <span class="font-bold text-red-600"><?= $erros ?></span></div>
                    <div class="text-sm font-semibold text-blue-600 mt-1">
                        <?= number_format($percentual, 2, ',', '.') ?>%
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="<?= URL ?>/aluno/provas/resultado/<?= $prova['id'] ?>" 
                   class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Ver Detalhes
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Botão Voltar -->
<div class="mt-6">
    <a href="<?= URL ?>/aluno/provas" 
       class="inline-block bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
        Voltar para Provas
    </a>
</div>


