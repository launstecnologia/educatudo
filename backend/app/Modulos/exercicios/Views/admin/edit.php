<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Editar Exercício 📝
            </h2>
            <p class="text-gray-600">
                Modifique os dados do exercício: <?= htmlspecialchars($exercicio['titulo']) ?>
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
        <form action="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>" method="POST" class="space-y-6">
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <!-- Informações Básicas -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">
                        Título do Exercício *
                    </label>
                    <input type="text" id="titulo" name="titulo" required
                           value="<?= htmlspecialchars($exercicio['titulo']) ?>"
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
                        <option value="Matemática" <?= $exercicio['materia'] === 'Matemática' ? 'selected' : '' ?>>Matemática</option>
                        <option value="Português" <?= $exercicio['materia'] === 'Português' ? 'selected' : '' ?>>Português</option>
                        <option value="História" <?= $exercicio['materia'] === 'História' ? 'selected' : '' ?>>História</option>
                        <option value="Geografia" <?= $exercicio['materia'] === 'Geografia' ? 'selected' : '' ?>>Geografia</option>
                        <option value="Ciências" <?= $exercicio['materia'] === 'Ciências' ? 'selected' : '' ?>>Ciências</option>
                        <option value="Física" <?= $exercicio['materia'] === 'Física' ? 'selected' : '' ?>>Física</option>
                        <option value="Química" <?= $exercicio['materia'] === 'Química' ? 'selected' : '' ?>>Química</option>
                        <option value="Biologia" <?= $exercicio['materia'] === 'Biologia' ? 'selected' : '' ?>>Biologia</option>
                        <option value="Inglês" <?= $exercicio['materia'] === 'Inglês' ? 'selected' : '' ?>>Inglês</option>
                        <option value="Educação Física" <?= $exercicio['materia'] === 'Educação Física' ? 'selected' : '' ?>>Educação Física</option>
                        <option value="Artes" <?= $exercicio['materia'] === 'Artes' ? 'selected' : '' ?>>Artes</option>
                        <option value="Filosofia" <?= $exercicio['materia'] === 'Filosofia' ? 'selected' : '' ?>>Filosofia</option>
                        <option value="Sociologia" <?= $exercicio['materia'] === 'Sociologia' ? 'selected' : '' ?>>Sociologia</option>
                    </select>
                </div>
                
                <div>
                    <label for="serie" class="block text-sm font-medium text-gray-700 mb-2">
                        Série *
                    </label>
                    <select id="serie" name="serie" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Selecione uma série</option>
                        <option value="6º ano do Ensino Fundamental" <?= $exercicio['serie'] === '6º ano do Ensino Fundamental' ? 'selected' : '' ?>>6º ano do Ensino Fundamental</option>
                        <option value="7º ano do Ensino Fundamental" <?= $exercicio['serie'] === '7º ano do Ensino Fundamental' ? 'selected' : '' ?>>7º ano do Ensino Fundamental</option>
                        <option value="8º ano do Ensino Fundamental" <?= $exercicio['serie'] === '8º ano do Ensino Fundamental' ? 'selected' : '' ?>>8º ano do Ensino Fundamental</option>
                        <option value="9º ano do Ensino Fundamental" <?= $exercicio['serie'] === '9º ano do Ensino Fundamental' ? 'selected' : '' ?>>9º ano do Ensino Fundamental</option>
                        <option value="1º ano do Ensino Médio" <?= $exercicio['serie'] === '1º ano do Ensino Médio' ? 'selected' : '' ?>>1º ano do Ensino Médio</option>
                        <option value="2º ano do Ensino Médio" <?= $exercicio['serie'] === '2º ano do Ensino Médio' ? 'selected' : '' ?>>2º ano do Ensino Médio</option>
                        <option value="3º ano do Ensino Médio" <?= $exercicio['serie'] === '3º ano do Ensino Médio' ? 'selected' : '' ?>>3º ano do Ensino Médio</option>
                    </select>
                </div>
                
                <div>
                    <label for="nivel_dificuldade" class="block text-sm font-medium text-gray-700 mb-2">
                        Nível de Dificuldade *
                    </label>
                    <select id="nivel_dificuldade" name="nivel_dificuldade" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="">Selecione o nível</option>
                        <option value="Fácil" <?= $exercicio['nivel_dificuldade'] === 'Fácil' ? 'selected' : '' ?>>Fácil</option>
                        <option value="Médio" <?= $exercicio['nivel_dificuldade'] === 'Médio' ? 'selected' : '' ?>>Médio</option>
                        <option value="Difícil" <?= $exercicio['nivel_dificuldade'] === 'Difícil' ? 'selected' : '' ?>>Difícil</option>
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
                          placeholder="Descreva o conteúdo e objetivos deste exercício..."><?= htmlspecialchars($exercicio['descricao']) ?></textarea>
            </div>
            
            <!-- Status -->
            <div>
                <label class="flex items-center space-x-3">
                    <input type="checkbox" name="ativo" value="1" 
                           <?= $exercicio['ativo'] ? 'checked' : '' ?>
                           class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                    <span class="text-sm font-medium text-gray-700">
                        Exercício ativo (disponível para alunos)
                    </span>
                </label>
            </div>
            
            <!-- Informações do Exercício -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="text-sm font-medium text-gray-800 mb-3">📊 Informações do Exercício</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">ID:</span>
                        <span class="font-medium text-gray-900"><?= $exercicio['id'] ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Criado em:</span>
                        <span class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($exercicio['created_at'])) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Última atualização:</span>
                        <span class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($exercicio['updated_at'])) ?></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Status:</span>
                        <span class="font-medium <?= $exercicio['ativo'] ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $exercicio['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="flex space-x-4 pt-6 border-t border-gray-200">
                <a href="<?= URL ?>/admin/exercises" 
                   class="flex-1 px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors text-center">
                    Cancelar
                </a>
                <button type="submit" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl">
                    Salvar Alterações
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
