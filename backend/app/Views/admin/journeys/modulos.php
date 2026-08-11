<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar Módulos - <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Prof. <?= htmlspecialchars($jornada['professor_nome']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?> • 
                <?= htmlspecialchars($jornada['turma_nome']) ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/jornadas/<?= $jornada['id'] ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Adicionar Novo Módulo -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-purple-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Adicionar Novo Módulo</h3>
    
    <form id="adicionarModuloForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Módulo *</label>
            <select name="tipo_modulo" required 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">Selecione o tipo</option>
                <option value="resumo_aluno">📝 Pedir Resumo para Aluno</option>
                <option value="dica_professor">💡 Dica do Professor</option>
                <option value="exercicios">✅ Exercícios</option>
                <option value="video">📚 Conteúdo</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
            <input type="text" name="titulo" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                   placeholder="Deixe vazio para usar o padrão">
        </div>
        
        <div class="md:col-span-2">
            <div class="flex items-center">
                <input type="checkbox" name="obrigatorio" id="obrigatorio" 
                       class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <label for="obrigatorio" class="ml-2 text-sm text-gray-700">Módulo obrigatório</label>
            </div>
        </div>
        
        <div class="md:col-span-2">
            <button type="submit" 
                    class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Adicionar Módulo
            </button>
        </div>
    </form>
</div>

<!-- Lista de Módulos -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-purple-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Módulos da Jornada</h3>
    
    <div id="modulosList" class="space-y-3">
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            <p>Nenhum módulo adicionado ainda</p>
            <p class="text-sm text-gray-400 mt-2">Adicione módulos usando o formulário acima</p>
        </div>
    </div>
</div>

<script>
function carregarModulos() {
    fetch('<?= URL ?>/admin/jornadas/<?= $jornada['id'] ?>/modulos/lista')
        .then(response => response.json())
        .then(data => {
            console.log('Dados recebidos do servidor:', data);
            if (data.success && data.modulos && data.modulos.length > 0) {
                renderizarModulos(data.modulos);
            } else {
                document.getElementById('modulosList').innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <p>Nenhum módulo adicionado ainda</p>
                        <p class="text-sm text-gray-400 mt-2">Adicione módulos usando o formulário acima</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro ao carregar módulos:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('adicionarModuloForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        fetch('<?= URL ?>/admin/jornadas/adicionar-modulo', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Módulo adicionado com sucesso!');
                form.reset();
                carregarModulos();
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            alert('Erro de conexão');
            console.error(error);
        });
    });
    
    // Carrega módulos ao carregar a página
    carregarModulos();
});

function renderizarModulos(modulos) {
    const icons = {
        'resumo_aluno': '📝',
        'dica_professor': '💡',
        'exercicios': '✅',
        'video': '📚',
        'videos': '📚',
        'conteudo': '📚'
    };
    
    const nomes = {
        'resumo_aluno': 'Pedir Resumo para Aluno',
        'dica_professor': 'Dica do Professor',
        'exercicios': 'Exercícios',
        'exercicio': 'Exercícios',
        'video': 'Conteúdo',
        'videos': 'Conteúdo',
        'conteudo': 'Conteúdo'
    };
    
    let html = '';
    modulos.forEach((modulo, index) => {
        // Debug: verifica o tipo do módulo
        const tipoModulo = (modulo.tipo_modulo || '').toString().trim();
        console.log('Módulo:', modulo.titulo || 'Sem título', 'Tipo:', tipoModulo, 'ID:', modulo.id, 'Objeto completo:', modulo);
        html += `
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors" data-modulo-id="${modulo.id}">
                <div class="flex justify-between items-start">
                    <div class="flex items-center space-x-3 flex-1">
                        <div class="text-2xl">${icons[tipoModulo] || '📦'}</div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900">${modulo.titulo || nomes[tipoModulo] || 'Módulo'}</h4>
                            <p class="text-sm text-gray-600">${nomes[tipoModulo] || tipoModulo || 'Tipo não definido'}</p>
                            ${modulo.descricao ? `<p class="text-sm text-gray-500 mt-1">${modulo.descricao}</p>` : ''}
                            ${modulo.aula_nome ? `<p class="text-xs text-purple-600 mt-1">Aula: ${modulo.aula_nome}</p>` : '<p class="text-xs text-gray-400 mt-1">Todas as aulas</p>'}
                            ${modulo.obrigatorio ? '<span class="inline-block mt-1 px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Obrigatório</span>' : ''}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 ml-4">
                        ${(['exercicios', 'exercicio'].includes(tipoModulo)) ? `
                            <a href="<?= URL ?>/admin/jornadas/modulos/${modulo.id}/exercicios" 
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                Gerenciar
                            </a>
                        ` : ''}
                        ${(['video', 'videos', 'conteudo'].includes(tipoModulo)) ? `
                            <a href="<?= URL ?>/admin/jornadas/modulos/${modulo.id}/videos" 
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                Gerenciar
                            </a>
                        ` : ''}
                        <button onclick="removerModulo(${modulo.id})" 
                                class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                            Remover
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('modulosList').innerHTML = html;
}

function removerModulo(moduloId) {
    if (!confirm('Tem certeza que deseja remover este módulo?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('modulo_id', moduloId);
    
    fetch('<?= URL ?>/admin/jornadas/remover-modulo', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Módulo removido com sucesso!');
            carregarModulos();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}
</script>

