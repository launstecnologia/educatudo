<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar Blocos - <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?> • 
                <?= htmlspecialchars($jornada['turma_nome']) ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                
                Voltar
            </a>
        </div>
    </div>
</div>

<style>
.select-safari-sem-icone {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: none !important;
    padding-right: 0.75rem;
}
</style>

<!-- Adicionar Novo Bloco -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-blue-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Adicionar Novo Bloco</h3>
    
    <form id="adicionarModuloForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Bloco *</label>
            <select name="tipo_modulo" required 
                    class="select-safari-sem-icone w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione o tipo</option>
                <option value="resumo_aluno">Pedir Resumo para Aluno</option>
                <option value="dica_professor">Dica do Professor</option>
                <option value="exercicios">Exercícios</option>
                <option value="video">Conteúdo</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
            <input type="text" name="titulo" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Deixe vazio para usar o padrão">
        </div>
        
        <div class="md:col-span-2">
            <div class="flex items-center">
                <input type="checkbox" name="obrigatorio" id="obrigatorio" 
                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="obrigatorio" class="ml-2 text-sm text-gray-700">Bloco obrigatório</label>
            </div>
        </div>
        
        <div class="md:col-span-2">
            <button type="submit" 
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                
                Adicionar Bloco
            </button>
        </div>
    </form>
</div>

<!-- Lista de Blocos -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Blocos da Jornada</h3>
    <p class="text-sm text-gray-500 mb-4">Arraste os blocos para reordená-los</p>
    
    <div id="modulosList" class="space-y-3">
        <div class="text-center py-8 text-gray-500">
            
            <p>Nenhum bloco adicionado ainda</p>
            <p class="text-sm text-gray-400 mt-2">Adicione blocos usando o formulário acima</p>
        </div>
    </div>
</div>

<!-- Modal Editar Bloco -->
<div id="editarModuloModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Editar Bloco</h4>
        <form id="editarModuloForm" class="space-y-4">
            <input type="hidden" name="modulo_id" id="editar_modulo_id">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
                <input type="text" name="titulo" id="editar_modulo_titulo" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="obrigatorio" id="editar_modulo_obrigatorio"
                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="editar_modulo_obrigatorio" class="ml-2 text-sm text-gray-700">Bloco obrigatório</label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="fecharEditarModuloModal()"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let moduloEditandoId = null;

function escapeJsString(value) {
    return String(value)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r');
}

function abrirEditarModuloModal(moduloId, titulo, obrigatorio) {
    moduloEditandoId = moduloId;
    document.getElementById('editar_modulo_id').value = moduloId;
    document.getElementById('editar_modulo_titulo').value = titulo || '';
    document.getElementById('editar_modulo_obrigatorio').checked = Number(obrigatorio) === 1;
    const modal = document.getElementById('editarModuloModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharEditarModuloModal() {
    const modal = document.getElementById('editarModuloModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    moduloEditandoId = null;
}

document.getElementById('editarModuloForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('<?= URL ?>/professor/jornadas/editar-modulo', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fecharEditarModuloModal();
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

function carregarModulos() {
    fetch('<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/modulos/lista')
        .then(response => response.json())
        .then(data => {
            console.log('Dados recebidos do servidor:', data);
            console.log('Módulos recebidos:', JSON.stringify(data.modulos, null, 2));
            
            // Debug: verifica se tipo_modulo está presente em cada módulo
            if (data.modulos) {
                data.modulos.forEach((modulo, index) => {
                    console.log(`Módulo ${index}: tipo_modulo = '${modulo.tipo_modulo}', titulo = '${modulo.titulo}'`);
                });
            }
            
            if (data.success && data.modulos && data.modulos.length > 0) {
                renderizarModulos(data.modulos);
            } else {
                document.getElementById('modulosList').innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        
                        <p>Nenhum bloco adicionado ainda</p>
                        <p class="text-sm text-gray-400 mt-2">Adicione blocos usando o formulário acima</p>
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
        
        // Validação antes de enviar
        const tipoModuloSelect = form.querySelector('select[name="tipo_modulo"]');
        const tituloInput = form.querySelector('input[name="titulo"]');
        
        const tipoModulo = tipoModuloSelect ? tipoModuloSelect.value : null;
        const titulo = tituloInput ? tituloInput.value.trim() : '';
        
        // Debug: verifica o que está sendo enviado
        console.log('=== ENVIANDO FORMULÁRIO ===');
        console.log('tipo_modulo (do select):', tipoModulo);
        console.log('titulo (do input):', titulo);
        console.log('tipo_modulo está vazio?', !tipoModulo || tipoModulo === '');
        console.log('==========================');
        
        // Validação no cliente
        if (!tipoModulo || tipoModulo === '') {
            alert('Por favor, selecione o tipo de bloco');
            if (tipoModuloSelect) {
                tipoModuloSelect.focus();
            }
            return;
        }
        
        const formData = new FormData(form);
        
        // Garante que tipo_modulo está no FormData
        if (!formData.get('tipo_modulo')) {
            formData.set('tipo_modulo', tipoModulo);
            console.log('⚠️ tipo_modulo não estava no FormData, adicionado manualmente');
        }
        
        // Debug final
        console.log('FormData final:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        
        fetch('<?= URL ?>/professor/jornadas/adicionar-modulo', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Bloco adicionado com sucesso!');
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
        // CRÍTICO: Garante que tipo_modulo existe e está limpo
        // Trata NULL, undefined, string vazia, etc.
        let tipoOriginal = '';
        if (modulo.tipo_modulo !== null && modulo.tipo_modulo !== undefined) {
            tipoOriginal = String(modulo.tipo_modulo).trim();
        }
        
        const tipoModulo = tipoOriginal.toLowerCase();
        const tituloModulo = (modulo.titulo || '').toString().trim().toLowerCase();
        
        // Log CRÍTICO para debug
        if (!tipoOriginal || tipoOriginal === '') {
            console.error('⚠️⚠️⚠️ MÓDULO SEM TIPO_MODULO!', {
                id: modulo.id,
                titulo: modulo.titulo,
                tipo_modulo_raw: modulo.tipo_modulo,
                tipo_modulo_type: typeof modulo.tipo_modulo,
                modulo_completo: modulo
            });
        }
        
        // Verifica se é dica do professor - PRIORIZA sempre o tipo_modulo do banco
        let isDicaProfessor = false;
        if (tipoOriginal === 'dica_professor' || tipoModulo === 'dica_professor') {
            isDicaProfessor = true;
        }
        
        // Se tipo_modulo está vazio, mostra "Tipo não definido"
        // Mas se temos um título que sugere o tipo, tenta inferir
        let nomeTipo = 'Tipo não definido';
        if (tipoModulo && tipoModulo !== '') {
            nomeTipo = nomes[tipoModulo] || tipoOriginal || 'Tipo não definido';
        } else if (tituloModulo) {
            // Fallback: tenta inferir do título (apenas para exibição, não para lógica)
            if (tituloModulo.includes('dica') || tituloModulo.includes('professor')) {
                nomeTipo = 'Dica do Professor';
            } else if (tituloModulo.includes('resumo')) {
                nomeTipo = 'Pedir Resumo para Aluno';
            } else if (tituloModulo.includes('exercício') || tituloModulo.includes('exercicio')) {
                nomeTipo = 'Exercícios';
            }
        }
        
        // Log detalhado para debug
        console.log('=== DEBUG MÓDULO ===');
        console.log('ID:', modulo.id);
        console.log('Título:', modulo.titulo);
        console.log('Tipo original (raw):', modulo.tipo_modulo);
        console.log('Tipo original (trimmed):', tipoOriginal);
        console.log('Tipo (lowercase):', tipoModulo);
        console.log('É dica_professor?', isDicaProfessor);
        console.log('Nome do tipo:', nomeTipo);
        console.log('Módulo completo:', JSON.stringify(modulo, null, 2));
        console.log('===================');
        const tituloEscapado = escapeJsString(modulo.titulo || nomes[tipoModulo] || 'Bloco');
        html += `
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors draggable-item" 
                 data-modulo-id="${modulo.id}" 
                 draggable="true"
                 style="position: relative;">
                <div class="flex justify-between items-start">
                    <div class="flex items-center space-x-3 flex-1">
                        <div class="drag-handle text-blue-500 text-sm font-semibold" style="cursor: grab; user-select: none;" title="Arraste para reordenar">☰</div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900">${modulo.titulo || nomes[tipoModulo] || 'Bloco'}</h4>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs">
                                <span class="text-gray-600">${nomeTipo}</span>
                                ${modulo.aula_nome ? `<span class="text-blue-600">Aula: ${modulo.aula_nome}</span>` : '<span class="text-gray-400">Todas as aulas</span>'}
                                ${modulo.obrigatorio ? '<span class="px-2 py-0.5 bg-red-100 text-red-800 rounded">Obrigatório</span>' : ''}
                            </div>
                            ${modulo.descricao ? `<p class="text-sm text-gray-500 mt-1">${modulo.descricao}</p>` : ''}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 ml-4 no-drag">
                        ${isDicaProfessor ? `
                            <a href="<?= URL ?>/professor/jornadas/modulos/${modulo.id}/dica-professor" 
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors"
                               title="Gerenciar">
                                
                                Gerenciar
                            </a>
                        ` : ''}
                        ${!isDicaProfessor && modulo.titulo && (modulo.titulo.toLowerCase().includes('dica') || modulo.titulo.toLowerCase().includes('professor')) ? `
                            <!-- Fallback: mostra botão mesmo se a lógica principal falhou -->
                            <a href="<?= URL ?>/professor/jornadas/modulos/${modulo.id}/dica-professor" 
                               class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600 transition-colors"
                               title="Gerenciar (Fallback)">
                                
                                Gerenciar
                            </a>
                        ` : ''}
                        ${tipoModulo === 'resumo_aluno' ? `
                            <a href="<?= URL ?>/professor/jornadas/modulos/${modulo.id}/resumo-aluno" 
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors"
                               title="Gerenciar">
                                
                                Gerenciar
                            </a>
                        ` : ''}
                        ${(['exercicios', 'exercicio'].includes(tipoModulo)) ? `
                            <a href="<?= URL ?>/professor/jornadas/modulos/${modulo.id}/exercicios" 
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors"
                               title="Gerenciar">
                                
                                Gerenciar
                            </a>
                        ` : ''}
                        ${(['video', 'videos', 'conteudo'].includes(tipoModulo)) ? `
                            <a href="<?= URL ?>/professor/jornadas/modulos/${modulo.id}/videos" 
                               class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors"
                               title="Gerenciar">
                                
                                Gerenciar
                            </a>
                        ` : ''}
                        <button onclick="abrirEditarModuloModal(${modulo.id}, '${tituloEscapado}', ${modulo.obrigatorio ? 1 : 0})" 
                                class="px-3 py-1 bg-amber-500 text-white rounded text-sm hover:bg-amber-600 transition-colors"
                                title="Editar">
                            
                            Editar
                        </button>
                        <button onclick="removerModulo(${modulo.id})" 
                                class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors"
                                title="Remover">
                            
                            Remover
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('modulosList').innerHTML = html;
    
    // Inicializa drag and drop após renderizar
    inicializarDragAndDrop();
}

function inicializarDragAndDrop() {
    const container = document.getElementById('modulosList');
    if (!container) return;
    
    const items = container.querySelectorAll('.draggable-item');
    let draggedElement = null;
    let draggedIndex = null;
    
    items.forEach((item, index) => {
        // Previne que links e botões iniciem o drag
        const noDragElements = item.querySelectorAll('.no-drag, .no-drag a, .no-drag button');
        noDragElements.forEach(el => {
            el.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });
            el.addEventListener('dragstart', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        // Permite drag apenas pelo handle ou pela área do item (exceto botões/links)
        const dragHandle = item.querySelector('.drag-handle');
        if (dragHandle) {
            dragHandle.addEventListener('mousedown', function(e) {
                item.draggable = true;
            });
        }
        
        item.addEventListener('mousedown', function(e) {
            // Se clicou em um elemento que não deve iniciar drag, cancela
            if (e.target.closest('.no-drag') || e.target.closest('a') || e.target.closest('button')) {
                item.draggable = false;
                return;
            }
            item.draggable = true;
        });
        
        item.addEventListener('dragstart', function(e) {
            // Se o drag foi iniciado por um elemento que não deve arrastar, cancela
            if (e.target.closest('.no-drag') || e.target.closest('a') || e.target.closest('button')) {
                e.preventDefault();
                return false;
            }
            
            draggedElement = this;
            draggedIndex = index;
            this.style.opacity = '0.5';
            this.style.cursor = 'grabbing';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.getAttribute('data-modulo-id'));
        });
        
        item.addEventListener('dragend', function(e) {
            this.style.opacity = '1';
            this.style.cursor = '';
            this.style.borderTop = '';
            this.style.borderBottom = '';
            // Remove todas as classes de destaque
            items.forEach(i => {
                i.classList.remove('border-blue-500', 'bg-blue-50');
                i.style.borderTop = '';
                i.style.borderBottom = '';
            });
            draggedElement = null;
            draggedIndex = null;
        });
        
        item.addEventListener('dragover', function(e) {
            // Previne o comportamento padrão
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
            
            if (draggedElement && draggedElement !== this) {
                const rect = this.getBoundingClientRect();
                const y = e.clientY - rect.top;
                
                // Remove destaque de todos os itens
                items.forEach(i => {
                    i.classList.remove('border-blue-500', 'bg-blue-50');
                    i.style.borderTop = '';
                    i.style.borderBottom = '';
                });
                
                // Adiciona destaque ao item atual
                this.classList.add('border-blue-500', 'bg-blue-50');
                
                // Adiciona uma linha indicadora
                if (y < rect.height / 2) {
                    this.style.borderTop = '3px solid #3b82f6';
                    this.style.borderBottom = '';
                } else {
                    this.style.borderBottom = '3px solid #3b82f6';
                    this.style.borderTop = '';
                }
            }
        });
        
        item.addEventListener('dragleave', function(e) {
            // Só remove o destaque se realmente saiu do elemento
            const rect = this.getBoundingClientRect();
            const x = e.clientX;
            const y = e.clientY;
            
            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                this.classList.remove('border-blue-500', 'bg-blue-50');
                this.style.borderTop = '';
                this.style.borderBottom = '';
            }
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.classList.remove('border-blue-500', 'bg-blue-50');
            this.style.borderTop = '';
            this.style.borderBottom = '';
            
            if (draggedElement && draggedElement !== this) {
                const rect = this.getBoundingClientRect();
                const y = e.clientY - rect.top;
                const currentIndex = Array.from(items).indexOf(this);
                
                if (y < rect.height / 2) {
                    // Insere antes
                    if (draggedIndex < currentIndex) {
                        container.insertBefore(draggedElement, this);
                    } else {
                        container.insertBefore(draggedElement, this);
                    }
                } else {
                    // Insere depois
                    const nextSibling = this.nextSibling;
                    if (nextSibling) {
                        container.insertBefore(draggedElement, nextSibling);
                    } else {
                        container.appendChild(draggedElement);
                    }
                }
                
                // Salva a nova ordem
                salvarOrdem();
            }
        });
    });
}

function salvarOrdem() {
    const container = document.getElementById('modulosList');
    const items = container.querySelectorAll('.draggable-item');
    const ordens = Array.from(items).map(item => item.getAttribute('data-modulo-id'));
    
    const formData = new FormData();
    formData.append('jornada_id', '<?= $jornada['id'] ?>');
    ordens.forEach((id, index) => {
        formData.append('ordens[]', id);
    });
    
    fetch('<?= URL ?>/professor/jornadas/atualizar-ordem-modulos', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Ordem atualizada com sucesso');
        } else {
            console.error('Erro ao atualizar ordem:', data.error);
            alert('Erro ao salvar ordem: ' + (data.error || 'Erro desconhecido'));
            // Recarrega para restaurar a ordem anterior
            carregarModulos();
        }
    })
    .catch(error => {
        console.error('Erro de conexão ao salvar ordem:', error);
        alert('Erro de conexão ao salvar ordem');
        // Recarrega para restaurar a ordem anterior
        carregarModulos();
    });
}

function removerModulo(moduloId) {
    if (!confirm('Tem certeza que deseja remover este bloco?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('modulo_id', moduloId);
    
    fetch('<?= URL ?>/professor/jornadas/remover-modulo', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bloco removido com sucesso!');
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
