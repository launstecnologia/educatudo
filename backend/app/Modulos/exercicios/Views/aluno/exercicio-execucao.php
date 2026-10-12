<?php
/**
 * View: Execução de Exercícios para Alunos (Sem Ajax)
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header com Cronômetro -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    📝 <?= htmlspecialchars($lista['titulo']) ?>
                </h1>
                <p class="text-gray-600">
                    <?= htmlspecialchars($lista['materia']) ?> • 
                    <?= htmlspecialchars($lista['nivel_dificuldade']) ?>
                </p>
            </div>
            
            <!-- Cronômetro -->
            <div class="text-right">
                <div id="cronometro" class="text-3xl font-bold text-blue-600 mb-2">
                    00:00:00
                </div>
                <div class="text-sm text-gray-600">
                    Tempo decorrido
                </div>
            </div>
        </div>
    </div>

    <!-- Progresso -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700">Progresso</span>
            <span class="text-sm font-medium text-gray-700">
                <?= $questao_atual ?> de <?= $total_questoes ?>
            </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                 style="width: <?= ($questao_atual / $total_questoes) * 100 ?>%"></div>
        </div>
    </div>

    <!-- Mensagens de Sucesso/Erro -->
    <?php if (isset($_GET['success'])): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        ✅ <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <!-- Questão Atual -->
    <?php 
    $questao = $questoes[$questao_atual - 1] ?? null;
    if ($questao): 
    ?>
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
        <!-- Cabeçalho da Questão -->
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">
                Questão <?= $questao['ordem'] ?> de <?= $total_questoes ?>
            </h3>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600">Tempo estimado:</span>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                    <?= gmdate('i:s', $questao['tempo_estimado']) ?>
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
        <form method="POST" action="<?= URL ?>/exercicios/responder" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="execucao_id" value="<?= $execucao_id ?>">
            <input type="hidden" name="questao_id" value="<?= $questao['id'] ?>">
            <input type="hidden" name="tempo_gasto" id="tempo_gasto" value="0">

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
                               class="mt-1 mr-3 text-blue-600 focus:ring-blue-500"
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
                    <a href="<?= URL ?>/exercicios/iniciar?id=<?= $lista['id'] ?>&questao=<?= $questao_atual - 1 ?>" 
                       class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        ← Anterior
                    </a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Questão:</span>
                    <select onchange="navegarParaQuestao(this.value)" class="px-3 py-1 border border-gray-300 rounded text-sm">
                        <?php for ($i = 1; $i <= $total_questoes; $i++): ?>
                            <option value="<?= $i ?>" <?= $i === $questao_atual ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <?php if ($questao_atual < $total_questoes): ?>
                    <button type="submit" name="proxima_questao" value="<?= $questao_atual + 1 ?>" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
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

    <!-- Botões de Ação -->
    <div class="flex justify-between items-center pt-6 border-t border-gray-200 mt-6">
        <a href="<?= URL ?>/exercicios" 
           class="px-6 py-3 border border-yellow-300 text-yellow-700 rounded-lg hover:bg-yellow-50 transition-colors">
            ⏸️ Pausar Exercício
        </a>
        
        <div class="text-center">
            <p class="text-sm text-gray-600 mb-2">Tempo decorrido:</p>
            <p id="tempoDecorrido" class="text-lg font-semibold text-gray-900">00:00:00</p>
        </div>
        
        <form method="POST" action="<?= URL ?>/exercicios/finalizar" class="inline">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="execucao_id" value="<?= $execucao_id ?>">
            <input type="hidden" name="tempo_total" id="tempo_total" value="0">
            <button type="submit" 
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                ✅ Finalizar Exercício
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let tempoInicio = Date.now();
    let cronometroInterval;
    
    // Inicializar cronômetro
    function iniciarCronometro() {
        cronometroInterval = setInterval(function() {
            const tempoDecorrido = Math.floor((Date.now() - tempoInicio) / 1000);
            
            const horas = Math.floor(tempoDecorrido / 3600);
            const minutos = Math.floor((tempoDecorrido % 3600) / 60);
            const segundos = tempoDecorrido % 60;
            
            const tempoFormatado = 
                String(horas).padStart(2, '0') + ':' +
                String(minutos).padStart(2, '0') + ':' +
                String(segundos).padStart(2, '0');
            
            document.getElementById('cronometro').textContent = tempoFormatado;
            document.getElementById('tempoDecorrido').textContent = tempoFormatado;
            
            // Atualizar campos hidden com o tempo atual
            document.getElementById('tempo_gasto').value = tempoDecorrido;
            document.getElementById('tempo_total').value = tempoDecorrido;
        }, 1000);
    }
    
    // Função para navegar para uma questão específica
    function navegarParaQuestao(questaoNumero) {
        window.location.href = '<?= URL ?>/exercicios/iniciar?id=<?= $lista['id'] ?>&questao=' + questaoNumero;
    }
    
    // Salvar tempo quando o formulário for enviado
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const tempoDecorrido = Math.floor((Date.now() - tempoInicio) / 1000);
            document.getElementById('tempo_gasto').value = tempoDecorrido;
            document.getElementById('tempo_total').value = tempoDecorrido;
        });
    });
    
    // Inicializar cronômetro
    iniciarCronometro();
});
</script>