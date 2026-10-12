<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Novo Exercício 📝
            </h2>
            <p class="text-gray-600">
                Preencha os dados para criar uma nova lista de exercícios
            </p>
        </div>
        <a href="<?= URL ?>/admin/exercises" 
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
        <form action="<?= URL ?>/admin/exercises" method="POST" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <!-- Informações Básicas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">
                        Título do Exercício *
                    </label>
                    <input type="text" id="titulo" name="titulo" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                           placeholder="Ex: Equações do 1º Grau">
                </div>
                
                <div>
                    <label for="materia" class="block text-sm font-medium text-gray-700 mb-2">
                        Matéria *
                    </label>
                    <select id="materia" name="materia" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Selecione uma matéria</option>
                        <option value="Matemática">Matemática</option>
                        <option value="Português">Português</option>
                        <option value="História">História</option>
                        <option value="Geografia">Geografia</option>
                        <option value="Ciências">Ciências</option>
                        <option value="Física">Física</option>
                        <option value="Química">Química</option>
                        <option value="Biologia">Biologia</option>
                        <option value="Inglês">Inglês</option>
                        <option value="Educação Física">Educação Física</option>
                        <option value="Artes">Artes</option>
                        <option value="Filosofia">Filosofia</option>
                        <option value="Sociologia">Sociologia</option>
                    </select>
                </div>
                
                <div>
                    <label for="serie" class="block text-sm font-medium text-gray-700 mb-2">
                        Série *
                    </label>
                    <select id="serie" name="serie" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Selecione uma série</option>
                        <option value="6º ano do Ensino Fundamental">6º ano do Ensino Fundamental</option>
                        <option value="7º ano do Ensino Fundamental">7º ano do Ensino Fundamental</option>
                        <option value="8º ano do Ensino Fundamental">8º ano do Ensino Fundamental</option>
                        <option value="9º ano do Ensino Fundamental">9º ano do Ensino Fundamental</option>
                        <option value="1º ano do Ensino Médio">1º ano do Ensino Médio</option>
                        <option value="2º ano do Ensino Médio">2º ano do Ensino Médio</option>
                        <option value="3º ano do Ensino Médio">3º ano do Ensino Médio</option>
                    </select>
                </div>
                
                <div>
                    <label for="nivel_dificuldade" class="block text-sm font-medium text-gray-700 mb-2">
                        Nível de Dificuldade *
                    </label>
                    <select id="nivel_dificuldade" name="nivel_dificuldade" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Selecione o nível</option>
                        <option value="Fácil">Fácil</option>
                        <option value="Médio">Médio</option>
                        <option value="Difícil">Difícil</option>
                    </select>
                </div>
            </div>
            
            <!-- Descrição -->
            <div>
                <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">
                    Descrição
                </label>
                <textarea id="descricao" name="descricao" rows="4"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                          placeholder="Descreva o conteúdo e objetivos deste exercício..."></textarea>
            </div>
            
            <!-- Informações Adicionais -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-blue-800 mb-2">ℹ️ Informações Importantes</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Após criar o exercício, você poderá adicionar questões individualmente</li>
                    <li>• Use o sistema de importação JSON para adicionar múltiplas questões de uma vez</li>
                    <li>• O exercício ficará disponível para alunos após ser ativado</li>
                </ul>
            </div>
            
            <!-- Botões -->
            <div class="flex space-x-4 pt-6 border-t border-gray-200">
                <a href="<?= URL ?>/admin/exercises" 
                   class="flex-1 px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors text-center">
                    Cancelar
                </a>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl">
                    Criar Exercício
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Validação em tempo real
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        function validateForm() {
            const titulo = document.getElementById('titulo').value.trim();
            const materia = document.getElementById('materia').value;
            const serie = document.getElementById('serie').value;
            const nivel = document.getElementById('nivel_dificuldade').value;
            
            const isValid = titulo && materia && serie && nivel;
            submitBtn.disabled = !isValid;
            
            if (isValid) {
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        // Validar em tempo real
        ['titulo', 'materia', 'serie', 'nivel_dificuldade'].forEach(id => {
            document.getElementById(id).addEventListener('input', validateForm);
        });
        
        // Validação inicial
        validateForm();
    });
</script>
