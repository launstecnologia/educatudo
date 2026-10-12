<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar Questões 📝
            </h2>
            <p class="text-gray-600">
                Lista: <strong><?= htmlspecialchars($exercicio['titulo']) ?></strong> 
                (<?= htmlspecialchars($exercicio['materia']) ?> - <?= htmlspecialchars($exercicio['serie']) ?>)
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions/create" 
               class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Nova Questão
            </a>
            <a href="<?= URL ?>/admin/exercises" 
               class="bg-gray-500 text-white px-6 py-3 rounded-xl hover:bg-gray-600 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Messages -->
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

<!-- Questions List -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Questões</h3>
        <p class="text-sm text-gray-600 mt-1">
            Total: <?= count($questoes) ?> questões
        </p>
    </div>
    
    <?php if (empty($questoes)): ?>
    <div class="p-12 text-center text-gray-500">
        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <p class="text-lg font-medium mb-2">Nenhuma questão cadastrada</p>
        <p class="mb-4">Comece adicionando a primeira questão para esta lista de exercícios.</p>
        <a href="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions/create" 
           class="btn-primary-custom inline-block px-6 py-3 rounded-lg text-sm transition-colors hover:opacity-90">
            Adicionar Primeira Questão
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pergunta</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resposta Correta</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="questionsTable">
                <?php foreach ($questoes as $questao): ?>
                <tr class="hover:bg-gray-50" data-question-id="<?= $questao['id'] ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs font-semibold">
                            <?= $questao['ordem'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="max-w-md">
                            <p class="font-medium truncate"><?= htmlspecialchars($questao['pergunta']) ?></p>
                            <div class="text-xs text-gray-500 mt-1">
                                <span class="bg-gray-100 px-2 py-1 rounded">A: <?= htmlspecialchars(substr($questao['alternativa_a'], 0, 30)) ?>...</span>
                                <span class="bg-gray-100 px-2 py-1 rounded ml-1">B: <?= htmlspecialchars(substr($questao['alternativa_b'], 0, 30)) ?>...</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold">
                            <?= $questao['resposta_correta'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $questao['tempo_estimado'] ?>s
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if ($questao['ativo']): ?>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold">
                                Ativa
                            </span>
                        <?php else: ?>
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-semibold">
                                Inativa
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex flex-wrap gap-1">
                            <a href="<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions/<?= $questao['id'] ?>/edit" 
                               class="text-blue-600 hover:text-blue-900 bg-blue-50 px-2 py-1 rounded text-xs">Editar</a>
                            <button onclick="deleteQuestion(<?= $questao['id'] ?>)" 
                                    class="text-red-600 hover:text-red-900 bg-red-50 px-2 py-1 rounded text-xs">Excluir</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Drag & Drop Instructions -->
<?php if (!empty($questoes)): ?>
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <h4 class="text-sm font-medium text-blue-800 mb-2">💡 Dica:</h4>
    <p class="text-sm text-blue-700">
        Você pode arrastar e soltar as questões para reordená-las. A ordem das questões afeta como elas aparecem para os alunos.
    </p>
</div>
<?php endif; ?>

<script>
    function deleteQuestion(questionId) {
        if (confirm('Tem certeza que deseja excluir esta questão? Esta ação não pode ser desfeita.')) {
            fetch(`<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions/${questionId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao excluir: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erro de conexão');
            });
        }
    }
    
    // Drag and Drop functionality
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('questionsTable');
        if (!table) return;
        
        let draggedElement = null;
        
        // Make rows draggable
        const rows = table.querySelectorAll('tr[data-question-id]');
        rows.forEach(row => {
            row.draggable = true;
            row.style.cursor = 'move';
            
            row.addEventListener('dragstart', function(e) {
                draggedElement = this;
                this.style.opacity = '0.5';
            });
            
            row.addEventListener('dragend', function(e) {
                this.style.opacity = '1';
                draggedElement = null;
            });
            
            row.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            row.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedElement && draggedElement !== this) {
                    const parent = this.parentNode;
                    const nextSibling = this.nextSibling;
                    parent.insertBefore(draggedElement, nextSibling);
                    
                    // Update order numbers
                    updateOrderNumbers();
                }
            });
        });
        
        function updateOrderNumbers() {
            const rows = table.querySelectorAll('tr[data-question-id]');
            const orders = {};
            
            rows.forEach((row, index) => {
                const questionId = row.getAttribute('data-question-id');
                orders[questionId] = index + 1;
                
                // Update visual order number
                const orderSpan = row.querySelector('span.bg-purple-100');
                if (orderSpan) {
                    orderSpan.textContent = index + 1;
                }
            });
            
            // Send new order to server
            fetch(`<?= URL ?>/admin/exercises/<?= $exercicio['id'] ?>/questions/reorder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `_token=<?= $csrf_token ?>&ordens=${JSON.stringify(orders)}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Erro ao reordenar:', data.error);
                    location.reload(); // Reload to fix order
                }
            })
            .catch(error => {
                console.error('Erro de conexão:', error);
                location.reload(); // Reload to fix order
            });
        }
    });
</script>
