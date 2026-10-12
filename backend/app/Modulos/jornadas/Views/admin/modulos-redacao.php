<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar Tema de Redação - <?= htmlspecialchars($modulo['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/jornadas/<?= $modulo['jornada_id'] ?>/modulos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Formulário de Tema -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-purple-200 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Configurar Tema da Redação</h3>
    
    <form id="formTemaRedacao" class="space-y-6" enctype="multipart/form-data">
        <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Tema -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tema da Redação <span class="text-red-500">*</span>
            </label>
            <input type="text" name="tema" required
                   value="<?= htmlspecialchars($redacao_jornada['tema_sugerido'] ?? '') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                   placeholder="Ex: Desafios da educação no Brasil contemporâneo">
            <p class="mt-1 text-sm text-gray-500">Tema que será apresentado aos alunos</p>
        </div>
        
        <!-- Descrição -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Descrição do Tema
            </label>
            <textarea name="descricao" rows="4"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                      placeholder="Descreva o tema, contexto e orientações para os alunos..."><?= htmlspecialchars($redacao_jornada['descricao_tema'] ?? '') ?></textarea>
            <p class="mt-1 text-sm text-gray-500">Descrição detalhada e orientações sobre o tema</p>
        </div>
        
        <!-- Imagem do Tema -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Imagem do Tema (Opcional)
            </label>
            <?php if (!empty($redacao_jornada['imagem_tema'])): ?>
                <div class="mb-3">
                    <img src="<?= URL ?>/<?= htmlspecialchars($redacao_jornada['imagem_tema']) ?>" 
                         alt="Imagem do tema" 
                         class="max-w-md rounded-lg border border-gray-300 shadow-sm">
                    <p class="text-sm text-gray-500 mt-2">Imagem atual</p>
                </div>
            <?php endif; ?>
            <input type="file" name="imagem_tema" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
            <p class="mt-1 text-sm text-gray-500">Formatos aceitos: JPG, PNG, GIF, WEBP (máx. 5MB)</p>
        </div>
        
        <!-- Correção Automática por IA -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-gray-700">
                <strong>Correção:</strong> Apenas o professor corrige as redações. O professor pode solicitar a correção da IA como referência para análise, mas a correção final é sempre do professor.
            </p>
            <input type="hidden" name="correcao_ia_automatica" value="0">
        </div>
        
        <div class="flex justify-end space-x-3 pt-4 border-t">
            <button type="submit" 
                    class="btn-primary-custom px-6 py-2 rounded-lg transition-colors font-medium hover:opacity-90">
                Salvar Tema
            </button>
        </div>
    </form>
</div>

<!-- Informações -->
<?php if ($redacao_jornada): ?>
<div class="bg-white rounded-xl shadow-lg p-6 border border-purple-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações</h3>
    <div class="space-y-2 text-sm">
        <p><span class="font-medium text-gray-700">Criado em:</span> 
           <span class="text-gray-600"><?= date('d/m/Y H:i', strtotime($redacao_jornada['created_at'])) ?></span></p>
        <?php if ($redacao_jornada['updated_at'] && $redacao_jornada['updated_at'] !== $redacao_jornada['created_at']): ?>
            <p><span class="font-medium text-gray-700">Atualizado em:</span> 
               <span class="text-gray-600"><?= date('d/m/Y H:i', strtotime($redacao_jornada['updated_at'])) ?></span></p>
        <?php endif; ?>
        <p><span class="font-medium text-gray-700">Status:</span> 
           <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
               <?= ucfirst($redacao_jornada['status']) ?>
           </span></p>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('formTemaRedacao').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/salvar-tema-redacao', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tema salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
});
</script>

