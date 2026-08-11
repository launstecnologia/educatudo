<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Adicionar Nova Questão 📝
            </h2>
            <p class="text-gray-600">
                Lista: <strong><?= htmlspecialchars($exercicio['titulo']) ?></strong> 
                (<?= htmlspecialchars($exercicio['materia']) ?> - <?= htmlspecialchars($exercicio['serie']) ?>)
            </p>
        </div>
        <a href="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions" 
           class="bg-gray-500 text-white px-6 py-3 rounded-xl hover:bg-gray-600 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<!-- Form -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6">
        <form action="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions" method="POST" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <!-- Pergunta -->
            <div>
                <label for="pergunta" class="block text-sm font-medium text-gray-700 mb-2">
                    Pergunta *
                </label>
                <textarea id="pergunta" name="pergunta" rows="3" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                          placeholder="Digite a pergunta aqui..."></textarea>
            </div>
            
            <!-- Alternativas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="alternativa_a" class="block text-sm font-medium text-gray-700 mb-2">
                        Alternativa A *
                    </label>
                    <input type="text" id="alternativa_a" name="alternativa_a" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Primeira alternativa">
                </div>
                
                <div>
                    <label for="alternativa_b" class="block text-sm font-medium text-gray-700 mb-2">
                        Alternativa B *
                    </label>
                    <input type="text" id="alternativa_b" name="alternativa_b" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Segunda alternativa">
                </div>
                
                <div>
                    <label for="alternativa_c" class="block text-sm font-medium text-gray-700 mb-2">
                        Alternativa C *
                    </label>
                    <input type="text" id="alternativa_c" name="alternativa_c" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Terceira alternativa">
                </div>
                
                <div>
                    <label for="alternativa_d" class="block text-sm font-medium text-gray-700 mb-2">
                        Alternativa D *
                    </label>
                    <input type="text" id="alternativa_d" name="alternativa_d" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Quarta alternativa">
                </div>
            </div>
            
            <!-- Resposta Correta e Configurações -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="resposta_correta" class="block text-sm font-medium text-gray-700 mb-2">
                        Resposta Correta *
                    </label>
                    <select id="resposta_correta" name="resposta_correta" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Selecione a resposta correta</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                
                <div>
                    <label for="tempo_estimado" class="block text-sm font-medium text-gray-700 mb-2">
                        Tempo Estimado (segundos)
                    </label>
                    <input type="number" id="tempo_estimado" name="tempo_estimado" 
                           value="120" min="30" max="600"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>
                
                <div>
                    <label for="ordem" class="block text-sm font-medium text-gray-700 mb-2">
                        Ordem na Lista
                    </label>
                    <input type="number" id="ordem" name="ordem" 
                           value="<?= $proxima_ordem ?>" min="1"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                </div>
            </div>
            
            <!-- Explicação -->
            <div>
                <label for="explicacao" class="block text-sm font-medium text-gray-700 mb-2">
                    Explicação da Resposta
                </label>
                <textarea id="explicacao" name="explicacao" rows="3"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                          placeholder="Explique por que esta é a resposta correta..."></textarea>
            </div>
            
            <!-- Preview da Questão -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-800 mb-3">👁️ Preview da Questão:</h4>
                <div id="questionPreview" class="text-sm text-gray-700">
                    <p class="font-medium mb-2">Pergunta aparecerá aqui...</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-white p-2 rounded border">A) Alternativa A aparecerá aqui</div>
                        <div class="bg-white p-2 rounded border">B) Alternativa B aparecerá aqui</div>
                        <div class="bg-white p-2 rounded border">C) Alternativa C aparecerá aqui</div>
                        <div class="bg-white p-2 rounded border">D) Alternativa D aparecerá aqui</div>
                    </div>
                </div>
            </div>
            
            <!-- Informações Adicionais -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-800 mb-2">ℹ️ Informações Importantes</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• A questão será adicionada na posição especificada na ordem</li>
                    <li>• Você pode alterar a ordem posteriormente arrastando as questões</li>
                    <li>• O tempo estimado ajuda os alunos a gerenciar seu tempo</li>
                    <li>• A explicação aparece após o aluno responder a questão</li>
                </ul>
            </div>
            
            <!-- Botões -->
            <div class="flex space-x-4 pt-6 border-t border-gray-200">
                <a href="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions" 
                   class="flex-1 px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors text-center">
                    Cancelar
                </a>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl">
                    Adicionar Questão
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Preview em tempo real
    document.addEventListener('DOMContentLoaded', function() {
        const perguntaInput = document.getElementById('pergunta');
        const altAInput = document.getElementById('alternativa_a');
        const altBInput = document.getElementById('alternativa_b');
        const altCInput = document.getElementById('alternativa_c');
        const altDInput = document.getElementById('alternativa_d');
        const previewDiv = document.getElementById('questionPreview');
        
        function updatePreview() {
            const pergunta = perguntaInput.value || 'Pergunta aparecerá aqui...';
            const altA = altAInput.value || 'Alternativa A aparecerá aqui';
            const altB = altBInput.value || 'Alternativa B aparecerá aqui';
            const altC = altCInput.value || 'Alternativa C aparecerá aqui';
            const altD = altDInput.value || 'Alternativa D aparecerá aqui';
            
            previewDiv.innerHTML = `
                <p class="font-medium mb-2">${pergunta}</p>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white p-2 rounded border">A) ${altA}</div>
                    <div class="bg-white p-2 rounded border">B) ${altB}</div>
                    <div class="bg-white p-2 rounded border">C) ${altC}</div>
                    <div class="bg-white p-2 rounded border">D) ${altD}</div>
                </div>
            `;
        }
        
        // Atualizar preview quando os campos mudarem
        [perguntaInput, altAInput, altBInput, altCInput, altDInput].forEach(input => {
            input.addEventListener('input', updatePreview);
        });
        
        // Validação em tempo real
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        function validateForm() {
            const pergunta = perguntaInput.value.trim();
            const altA = altAInput.value.trim();
            const altB = altBInput.value.trim();
            const altC = altCInput.value.trim();
            const altD = altDInput.value.trim();
            const respostaCorreta = document.getElementById('resposta_correta').value;
            
            const isValid = pergunta && altA && altB && altC && altD && respostaCorreta;
            submitBtn.disabled = !isValid;
            
            if (isValid) {
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        // Validar em tempo real
        [perguntaInput, altAInput, altBInput, altCInput, altDInput, document.getElementById('resposta_correta')].forEach(input => {
            input.addEventListener('input', validateForm);
        });
        
        // Validação inicial
        validateForm();
    });
</script>
