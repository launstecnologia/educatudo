<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                📝 Gerenciar Resumo do Aluno - <?= htmlspecialchars($modulo['titulo'] ?? 'Módulo') ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $modulo['jornada_id'] ?>/modulos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Formulário de Descrição -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Instruções para o Resumo</h3>
        <p class="text-sm text-gray-600">
            Defina aqui a descrição ou instrução que será exibida para os alunos quando eles forem fazer o resumo.
            Esta descrição aparecerá na tela do aluno como orientação sobre o que deve ser incluído no resumo.
        </p>
    </div>
    
    <form id="descricaoResumoForm" class="space-y-4">
        <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div>
            <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">
                Descrição/Instruções para o Resumo *
            </label>
            <textarea id="descricao" name="descricao" rows="8" required
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Ex: Escreva um resumo sobre o conteúdo estudado, destacando os pontos principais e suas conclusões..."><?= htmlspecialchars($modulo['descricao'] ?? '') ?></textarea>
            <p class="text-xs text-gray-500 mt-1">
                Esta descrição será exibida para os alunos como orientação sobre o que deve ser incluído no resumo.
            </p>
        </div>
        
        <div class="flex justify-end space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $modulo['jornada_id'] ?>/modulos" 
               class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Descrição
            </button>
        </div>
    </form>
</div>

<!-- Preview da Descrição -->
<?php if (!empty($modulo['descricao'])): ?>
<div class="bg-blue-50 rounded-xl shadow-lg p-6 border border-blue-200 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Preview - Como aparecerá para o aluno:</h3>
    <div class="bg-white rounded-lg p-4 border border-blue-200">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                <strong>📝 Instruções:</strong> <?= nl2br(htmlspecialchars($modulo['descricao'])) ?>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('descricaoResumoForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        fetch('<?= URL ?>/professor/jornadas/modulos/<?= $modulo['id'] ?>/salvar-descricao-resumo', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Descrição salva com sucesso!');
                // Recarrega a página para atualizar o preview
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            alert('Erro de conexão');
            console.error(error);
        });
    });
});
</script>

