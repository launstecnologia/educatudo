<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($sessao['lista_titulo']) ?></h1>
        <p class="text-gray-600">Responda as questões abaixo</p>
    </div>

    <!-- Questão Atual -->
    <?php 
    $questao = $questoes[$questao_atual - 1] ?? null;
    if ($questao): 
    ?>
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
        <!-- Cabeçalho da Questão -->
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">
                Questão <?= $questao_atual ?> de <?= count($questoes) ?>
            </h3>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm font-medium rounded-full">
                    <?= htmlspecialchars($questao['nivel_dificuldade'] ?? 'Médio') ?>
                </span>
            </div>
        </div>

        <!-- Enunciado -->
        <div class="mb-6">
            <div class="prose max-w-none">
                <p class="text-gray-800 text-lg leading-relaxed">
                    <?= nl2br(htmlspecialchars($questao['pergunta'])) ?>
                </p>
            </div>
        </div>

        <!-- Formulário de Resposta -->
        <form method="POST" action="<?= URL ?>/exercicios-personalizados/executar?sessao_id=<?= $sessao['id'] ?>&questao=<?= $questao_atual ?>" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="questao_id" value="<?= $questao['id'] ?>">

            <!-- Alternativas -->
            <div class="space-y-3">
                <?php 
                $alternativas = [
                    'A' => $questao['alternativa_a'],
                    'B' => $questao['alternativa_b'],
                    'C' => $questao['alternativa_c'],
                    'D' => $questao['alternativa_d']
                ];
                
                // Adicionar alternativa E se existir
                if (!empty($questao['alternativa_e'])) {
                    $alternativas['E'] = $questao['alternativa_e'];
                }
                ?>
                
                <?php foreach ($alternativas as $letra => $texto): ?>
                    <label class="flex items-start p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                        <input type="radio" 
                               name="resposta_escolhida" 
                               value="<?= $letra ?>"
                               class="mt-1 mr-3 text-purple-600 focus:ring-purple-500"
                               <?= $questao['resposta_escolhida'] === $letra ? 'checked' : '' ?>>
                        <div class="flex-1">
                            <span class="font-medium text-gray-900 mr-2"><?= $letra ?>)</span>
                            <span class="text-gray-700"><?= htmlspecialchars($texto) ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Botões de Navegação -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200 mt-6">
                <?php if ($questao_atual > 1): ?>
                    <a href="<?= URL ?>/exercicios-personalizados/executar?sessao_id=<?= $sessao['id'] ?>&questao=<?= $questao_atual - 1 ?>" 
                       class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        ← Anterior
                    </a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Questão:</span>
                    <select onchange="navegarParaQuestao(this.value)" class="px-3 py-1 border border-gray-300 rounded text-sm">
                        <?php for ($i = 1; $i <= count($questoes); $i++): ?>
                            <option value="<?= $i ?>" <?= $i === $questao_atual ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <?php if ($questao_atual < count($questoes)): ?>
                    <button type="submit" name="proxima_questao" value="<?= $questao_atual + 1 ?>" 
                            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        Próxima →
                    </button>
                <?php else: ?>
                    <button type="submit" name="proxima_questao" value="0" 
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Finalizar →
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// Função para navegar para uma questão específica
function navegarParaQuestao(questaoNumero) {
    window.location.href = '<?= URL ?>/exercicios-personalizados/executar?sessao_id=<?= $sessao['id'] ?>&questao=' + questaoNumero;
}
</script>
