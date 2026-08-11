<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Exercícios Interativos 📚</h1>
            <p class="text-gray-600 mt-2">Pratique com exercícios de múltipla escolha e acompanhe seu progresso!</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <p class="text-sm text-gray-500">Exercícios Realizados</p>
                <p class="text-lg font-semibold text-green-600"><?= count($exercicios_realizados) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex items-center space-x-4">
            <div class="relative">
                <input type="text" id="searchInput" placeholder="Buscar exercícios..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <select id="materiaFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="">Todas as matérias</option>
                <option value="Matemática">Matemática</option>
                <option value="Português">Português</option>
                <option value="História">História</option>
                <option value="Geografia">Geografia</option>
                <option value="Ciências">Ciências</option>
                <option value="Física">Física</option>
                <option value="Química">Química</option>
                <option value="Biologia">Biologia</option>
                <option value="Inglês">Inglês</option>
            </select>
            <select id="nivelFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="">Todos os níveis</option>
                <option value="Fácil">Fácil</option>
                <option value="Médio">Médio</option>
                <option value="Difícil">Difícil</option>
            </select>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Exibindo:</span>
            <span id="exerciseCount" class="text-sm font-medium text-green-600"><?= count($listas_exercicios) ?> listas</span>
        </div>
    </div>
</div>

<!-- Exercise Lists Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="exercisesGrid">
    <?php if (empty($listas_exercicios)): ?>
        <div class="col-span-full">
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma lista de exercícios encontrada</h3>
                <p class="text-gray-600">Aguarde seus professores criarem listas de exercícios para sua turma.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($listas_exercicios as $lista): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow exercise-card" 
                 data-titulo="<?= strtolower(htmlspecialchars($lista['titulo'])) ?>"
                 data-materia="<?= strtolower(htmlspecialchars($lista['materia'])) ?>"
                 data-nivel="<?= strtolower(htmlspecialchars($lista['nivel_dificuldade'])) ?>">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2"><?= htmlspecialchars($lista['titulo']) ?></h3>
                        <p class="text-gray-600 text-sm"><?= htmlspecialchars($lista['materia']) ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?= htmlspecialchars($lista['nivel_dificuldade']) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-green-50 rounded-lg p-3">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm text-gray-600">Exercícios</p>
                                <p class="text-lg font-semibold text-green-600"><?= $lista['total_exercicios'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 rounded-lg p-3">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm text-gray-600">Tempo Est.</p>
                                <p class="text-lg font-semibold text-blue-600"><?= $lista['total_exercicios'] * 2 ?>min</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info -->
                <div class="space-y-2 mb-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Tema:</span>
                        <span class="font-medium text-gray-900"><?= htmlspecialchars($lista['tema']) ?></span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Criado:</span>
                        <span class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($lista['created_at'])) ?></span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex space-x-2">
                    <button onclick="startExercise(<?= $lista['id'] ?>)" 
                            class="flex-1 bg-green-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Iniciar
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Completed Exercises -->
<?php if (!empty($exercicios_realizados)): ?>
<div class="mt-8 bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Exercícios Realizados</h2>
    <div class="space-y-4">
        <?php foreach ($exercicios_realizados as $exercicio): ?>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($exercicio['lista_titulo']) ?></h3>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($exercicio['materia']) ?></p>
                    <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($exercicio['started_at'])) ?></p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Acertos</p>
                        <p class="text-lg font-semibold text-green-600"><?= $exercicio['acertos'] ?>/<?= $exercicio['total_respostas'] ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Nota</p>
                        <p class="text-lg font-semibold text-blue-600"><?= round(($exercicio['acertos'] / $exercicio['total_respostas']) * 100) ?>%</p>
                    </div>
                    <button onclick="viewExerciseResults(<?= $exercicio['id'] ?>)" 
                            class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition-colors">
                        Ver Resultado
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Exercise Modal -->
<div id="exerciseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900" id="exerciseTitle">Exercício</h3>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="exerciseProgress">1/5</span>
                        <button onclick="closeExerciseModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div id="exerciseContent">
                    <!-- Exercise content will be loaded here -->
                </div>
                
                <div class="mt-6 flex justify-between">
                    <button id="prevButton" onclick="previousExercise()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors" disabled>
                        Anterior
                    </button>
                    <button id="nextButton" onclick="nextExercise()" 
                            class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                        Próximo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentExerciseList = [];
let currentExerciseIndex = 0;
let currentSessionId = null;
let userAnswers = {};

// Filter exercises
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const materiaFilter = document.getElementById('materiaFilter');
    const nivelFilter = document.getElementById('nivelFilter');
    const exercisesGrid = document.getElementById('exercisesGrid');
    const exerciseCount = document.getElementById('exerciseCount');
    const exerciseCards = document.querySelectorAll('.exercise-card');
    
    function filterExercises() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedMateria = materiaFilter.value.toLowerCase();
        const selectedNivel = nivelFilter.value.toLowerCase();
        let visibleCount = 0;
        
        exerciseCards.forEach(card => {
            const titulo = card.dataset.titulo;
            const materia = card.dataset.materia;
            const nivel = card.dataset.nivel;
            
            const matchesSearch = titulo.includes(searchTerm);
            const matchesMateria = !selectedMateria || materia.includes(selectedMateria);
            const matchesNivel = !selectedNivel || nivel.includes(selectedNivel);
            
            if (matchesSearch && matchesMateria && matchesNivel) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        exerciseCount.textContent = `${visibleCount} listas`;
    }
    
    searchInput.addEventListener('input', filterExercises);
    materiaFilter.addEventListener('change', filterExercises);
    nivelFilter.addEventListener('change', filterExercises);
});

// Start exercise
function startExercise(listaId) {
    const formData = new FormData();
    formData.append('lista_id', listaId);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    fetch('<?= URL ?>/exercicios/iniciar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentExerciseList = data.exercicios;
            currentSessionId = data.sessao_id;
            currentExerciseIndex = 0;
            userAnswers = {};
            
            openExerciseModal();
            loadCurrentExercise();
        } else {
            showNotification(data.error || 'Erro ao iniciar exercício', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Open exercise modal
function openExerciseModal() {
    document.getElementById('exerciseModal').classList.remove('hidden');
}

// Close exercise modal
function closeExerciseModal() {
    document.getElementById('exerciseModal').classList.add('hidden');
    currentExerciseList = [];
    currentExerciseIndex = 0;
    currentSessionId = null;
    userAnswers = {};
}

// Load current exercise
function loadCurrentExercise() {
    if (currentExerciseIndex >= currentExerciseList.length) {
        finishExercise();
        return;
    }
    
    const exercise = currentExerciseList[currentExerciseIndex];
    const exerciseContent = document.getElementById('exerciseContent');
    const exerciseTitle = document.getElementById('exerciseTitle');
    const exerciseProgress = document.getElementById('exerciseProgress');
    
    exerciseTitle.textContent = `Exercício ${currentExerciseIndex + 1}`;
    exerciseProgress.textContent = `${currentExerciseIndex + 1}/${currentExerciseList.length}`;
    
    exerciseContent.innerHTML = `
        <div class="mb-6">
            <h4 class="text-lg font-medium text-gray-900 mb-4">${exercise.pergunta}</h4>
            <div class="space-y-3">
                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="answer" value="A" class="mr-3">
                    <span class="font-medium">A)</span>
                    <span class="ml-2">${exercise.alternativa_a}</span>
                </label>
                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="answer" value="B" class="mr-3">
                    <span class="font-medium">B)</span>
                    <span class="ml-2">${exercise.alternativa_b}</span>
                </label>
                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="answer" value="C" class="mr-3">
                    <span class="font-medium">C)</span>
                    <span class="ml-2">${exercise.alternativa_c}</span>
                </label>
                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="answer" value="D" class="mr-3">
                    <span class="font-medium">D)</span>
                    <span class="ml-2">${exercise.alternativa_d}</span>
                </label>
            </div>
        </div>
        
        <div id="exerciseFeedback" class="hidden mb-4 p-4 rounded-lg">
            <div id="feedbackContent"></div>
        </div>
    `;
    
    // Update navigation buttons
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    
    prevButton.disabled = currentExerciseIndex === 0;
    
    if (currentExerciseIndex === currentExerciseList.length - 1) {
        nextButton.textContent = 'Finalizar';
    } else {
        nextButton.textContent = 'Próximo';
    }
    
    // Check if user already answered this question
    if (userAnswers[currentExerciseIndex]) {
        const selectedAnswer = userAnswers[currentExerciseIndex];
        document.querySelector(`input[value="${selectedAnswer}"]`).checked = true;
    }
}

// Submit answer
function submitAnswer() {
    const selectedAnswer = document.querySelector('input[name="answer"]:checked');
    if (!selectedAnswer) {
        showNotification('Selecione uma resposta', 'error');
        return;
    }
    
    const answer = selectedAnswer.value;
    const exercise = currentExerciseList[currentExerciseIndex];
    
    // Save user answer
    userAnswers[currentExerciseIndex] = answer;
    
    // Submit to server
    const formData = new FormData();
    formData.append('sessao_id', currentSessionId);
    formData.append('exercicio_id', exercise.id);
    formData.append('resposta', answer);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    fetch('<?= URL ?>/exercicios/resposta', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showFeedback(data.is_correct, data.explicacao);
        } else {
            showNotification(data.error || 'Erro ao enviar resposta', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Show feedback
function showFeedback(isCorrect, explanation) {
    const feedbackDiv = document.getElementById('exerciseFeedback');
    const feedbackContent = document.getElementById('feedbackContent');
    
    feedbackDiv.classList.remove('hidden');
    
    if (isCorrect) {
        feedbackDiv.className = 'mb-4 p-4 rounded-lg bg-green-100 border border-green-200';
        feedbackContent.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="font-medium text-green-800">Correto!</span>
            </div>
            <p class="text-green-700 mt-2">${explanation}</p>
        `;
    } else {
        feedbackDiv.className = 'mb-4 p-4 rounded-lg bg-red-100 border border-red-200';
        feedbackContent.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span class="font-medium text-red-800">Incorreto</span>
            </div>
            <p class="text-red-700 mt-2">${explanation}</p>
        `;
    }
}

// Previous exercise
function previousExercise() {
    if (currentExerciseIndex > 0) {
        currentExerciseIndex--;
        loadCurrentExercise();
    }
}

// Next exercise
function nextExercise() {
    const selectedAnswer = document.querySelector('input[name="answer"]:checked');
    if (!selectedAnswer) {
        showNotification('Selecione uma resposta antes de continuar', 'error');
        return;
    }
    
    submitAnswer();
    
    setTimeout(() => {
        if (currentExerciseIndex < currentExerciseList.length - 1) {
            currentExerciseIndex++;
            loadCurrentExercise();
        } else {
            finishExercise();
        }
    }, 2000);
}

// Finish exercise
function finishExercise() {
    closeExerciseModal();
    showNotification('Exercício concluído! Parabéns!');
    setTimeout(() => {
        location.reload();
    }, 2000);
}

// View exercise results
function viewExerciseResults(sessaoId) {
    // Implementar visualização de resultados
    showNotification('Funcionalidade em desenvolvimento');
}
</script>
