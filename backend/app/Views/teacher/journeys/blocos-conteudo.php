<?php
/**
 * View para gerenciar blocos de conteúdo da jornada
 */
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gerenciar Blocos de Conteúdo</h1>
                    <p class="text-gray-600 mt-1">Jornada: <?= htmlspecialchars($jornada['titulo']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($jornada['materia_nome']) ?> • <?= htmlspecialchars($jornada['turma_nome']) ?></p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="window.location.href='<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>'" 
                            class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        ← Voltar para Jornada
                    </button>
                </div>
            </div>
        </div>

        <!-- Adicionar Novo Bloco -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Adicionar Novo Bloco</h2>
            
            <form id="adicionarBlocoForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Bloco *</label>
                    <select name="tipo_bloco_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione o tipo</option>
                        <?php foreach ($tipos_blocos as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>">
                                <?= htmlspecialchars($tipo['icone']) ?> <?= htmlspecialchars($tipo['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                    <input type="text" name="titulo" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: Resumo da Aula">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tempo Estimado (min)</label>
                    <input type="number" name="tempo_estimado" min="1" max="120"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="15">
                </div>
                
                <div class="flex items-end">
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" name="obrigatorio" id="obrigatorio" checked 
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="obrigatorio" class="text-sm font-medium text-gray-700">Obrigatório</label>
                    </div>
                    <button type="submit" 
                            class="ml-4 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        ➕ Adicionar
                    </button>
                </div>
                
                <div class="md:col-span-2 lg:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <textarea name="descricao" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Descrição opcional do bloco..."></textarea>
                </div>
            </form>
        </div>

        <!-- Lista de Blocos -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Blocos da Jornada</h2>
                <p class="text-sm text-gray-500">Arraste e solte para reordenar</p>
            </div>
            
            <div id="blocosList" class="space-y-3">
                <?php if (empty($blocos)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p class="text-lg">Nenhum bloco criado ainda</p>
                        <p class="text-sm">Adicione blocos usando o formulário acima</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($blocos as $bloco): ?>
                        <div class="bloco-item bg-gray-50 border border-gray-200 rounded-lg p-4 cursor-move hover:bg-gray-100 transition-colors" 
                             data-bloco-id="<?= $bloco['id'] ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="text-2xl"><?= htmlspecialchars($bloco['icone']) ?></div>
                                    <div>
                                        <h3 class="font-medium text-gray-900"><?= htmlspecialchars($bloco['titulo']) ?></h3>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($bloco['tipo_nome']) ?></p>
                                        <?php if ($bloco['descricao']): ?>
                                            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($bloco['descricao']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                                        <?php if ($bloco['tempo_estimado']): ?>
                                            <span>⏱️ <?= $bloco['tempo_estimado'] ?>min</span>
                                        <?php endif; ?>
                                        <?php if ($bloco['obrigatorio']): ?>
                                            <span class="text-red-600">🔒 Obrigatório</span>
                                        <?php else: ?>
                                            <span class="text-green-600">🔓 Opcional</span>
                                        <?php endif; ?>
                                        <span class="text-blue-600">#<?= $bloco['ordem'] ?></span>
                                    </div>
                                    
                                    <div class="flex space-x-1">
                                        <button onclick="editarBloco(<?= $bloco['id'] ?>)" 
                                                class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors"
                                                title="Editar">
                                            ✏️
                                        </button>
                                        <button onclick="removerBloco(<?= $bloco['id'] ?>)" 
                                                class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-colors"
                                                title="Remover">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Editar Bloco -->
<div id="editarBlocoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Editar Bloco</h3>
                    <button onclick="fecharModalEditar()" class="text-gray-400 hover:text-gray-600">
                        ✕
                    </button>
                </div>
                
                <form id="editarBlocoForm">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="bloco_id" id="editar_bloco_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                            <input type="text" name="titulo" id="editar_titulo" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                            <textarea name="descricao" id="editar_descricao" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Conteúdo</label>
                            <textarea name="conteudo" id="editar_conteudo" rows="6"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Conteúdo específico do bloco..."></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tempo Estimado (min)</label>
                                <input type="number" name="tempo_estimado" id="editar_tempo_estimado" min="1" max="120"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" name="obrigatorio" id="editar_obrigatorio" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <label for="editar_obrigatorio" class="text-sm font-medium text-gray-700">Obrigatório</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="fecharModalEditar()" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// Inicializa drag and drop
let sortable = Sortable.create(document.getElementById('blocosList'), {
    animation: 150,
    ghostClass: 'opacity-50',
    onEnd: function(evt) {
        atualizarOrdemBlocos();
    }
});

// Adicionar novo bloco
document.getElementById('adicionarBlocoForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/blocos/adicionar', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro de conexão: ' + error.message);
    }
});

// Atualizar ordem dos blocos
async function atualizarOrdemBlocos() {
    const blocos = Array.from(document.querySelectorAll('.bloco-item')).map(item => 
        item.getAttribute('data-bloco-id')
    );
    
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    formData.append('ordens', JSON.stringify(blocos));
    
    try {
        const response = await fetch('<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/blocos/atualizar-ordem', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            alert('Erro ao atualizar ordem: ' + result.error);
            location.reload();
        }
    } catch (error) {
        alert('Erro de conexão: ' + error.message);
        location.reload();
    }
}

// Editar bloco
async function editarBloco(blocoId) {
    // Aqui você pode implementar a busca dos dados do bloco via AJAX
    // Por simplicidade, vou abrir o modal vazio
    document.getElementById('editar_bloco_id').value = blocoId;
    document.getElementById('editarBlocoModal').classList.remove('hidden');
}

// Fechar modal de editar
function fecharModalEditar() {
    document.getElementById('editarBlocoModal').classList.add('hidden');
}

// Salvar edição do bloco
document.getElementById('editarBlocoForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const blocoId = formData.get('bloco_id');
    
    try {
        const response = await fetch('<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/blocos/' + blocoId + '/editar', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            fecharModalEditar();
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro de conexão: ' + error.message);
    }
});

// Remover bloco
async function removerBloco(blocoId) {
    if (!confirm('Tem certeza que deseja remover este bloco? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch('<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/blocos/' + blocoId + '/remover', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                _token: <?= json_encode($csrf_token) ?>
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro de conexão: ' + error.message);
    }
}
</script>

