<?php
/**
 * Criar Bloco Modelo
 * Acesso: Coordenação
 */
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Bloco Modelo 📋
            </h2>
            <p class="text-gray-600">
                Crie um template reutilizável para agilizar a criação de Blocos de Prova
            </p>
        </div>
        <a href="<?= URL ?>/admin/blocos-modelo" 
           class="text-gray-600 hover:text-gray-900">
            ← Voltar
        </a>
    </div>
</div>

<!-- Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="formModelo" onsubmit="salvarModelo(event)">
        <!-- Nome -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Nome do Bloco <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="nome" 
                   name="nome" 
                   required
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="Ex: Bloco A, Bloco Simulado ENEM">
        </div>

        <!-- Descrição -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Descrição
            </label>
            <textarea id="descricao" 
                      name="descricao" 
                      rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Descrição opcional do modelo"></textarea>
        </div>

        <!-- Professores, Matérias e Número de Questões -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Professores e Matérias <span class="text-red-500">*</span>
            </label>
            <p class="text-sm text-gray-500 mb-4">Adicione professores com suas matérias e número de questões:</p>
            
            <div id="professoresContainer" class="space-y-4">
                <!-- Professores serão adicionados aqui via JavaScript -->
            </div>

            <div class="mt-4">
                <button type="button" 
                        onclick="adicionarProfessor()"
                        class="btn-primary-custom px-4 py-2 text-sm font-semibold rounded-lg transition-colors hover:opacity-90">
                    + Adicionar Professor
                </button>
            </div>
        </div>

        <!-- Botões -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/blocos-modelo" 
               class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" 
                    class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">
                Criar Modelo
            </button>
        </div>
    </form>
</div>

<script>
const professores = <?= json_encode($professores ?? [], JSON_UNESCAPED_UNICODE) ?>;
const materias = <?= json_encode($materias ?? [], JSON_UNESCAPED_UNICODE) ?>;
let professorCounter = 0;

console.log('=== DADOS CARREGADOS ===');
console.log('Professores:', professores);
console.log('Matérias:', materias);
professores.forEach(p => {
    console.log(`Professor: ${p.nome}, Matérias:`, p.materias);
});

function adicionarProfessor() {
    professorCounter++;
    const container = document.getElementById('professoresContainer');
    
    const professorDiv = document.createElement('div');
    professorDiv.className = 'border border-gray-300 rounded-lg p-4 bg-gray-50';
    professorDiv.id = `professor_${professorCounter}`;
    
    professorDiv.innerHTML = `
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-gray-700">Professor ${professorCounter}</h4>
            <button type="button" 
                    onclick="removerProfessor(${professorCounter})"
                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                ✕ Remover
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Professor <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][professor_id]" 
                        required
                        onchange="carregarMateriasProfessor(${professorCounter})"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Selecione o professor</option>
                    ${professores.map(p => {
                        // Garante que materias seja um array JSON válido
                        let materiasArray = [];
                        if (Array.isArray(p.materias)) {
                            materiasArray = p.materias;
                        } else if (typeof p.materias === 'string' && p.materias) {
                            try {
                                materiasArray = JSON.parse(p.materias);
                            } catch(e) {
                                materiasArray = [];
                            }
                        }
                        const materiasJson = JSON.stringify(materiasArray);
                        console.log(`Professor ${p.nome}: matérias =`, materiasArray);
                        return `
                        <option value="${p.id}" 
                                data-materias='${materiasJson}'>
                            ${p.nome}
                        </option>
                    `;
                    }).join('')}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Matéria <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][materia_id]" 
                        id="materia_${professorCounter}"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Selecione primeiro o professor</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Número de Questões <span class="text-red-500">*</span>
                </label>
                <input type="number" 
                       name="professores[${professorCounter}][numero_questoes]" 
                       min="1" 
                       value="5"
                       required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>
    `;
    
    container.appendChild(professorDiv);
}

function removerProfessor(id) {
    const professorDiv = document.getElementById(`professor_${id}`);
    if (professorDiv) {
        professorDiv.remove();
    }
}

function carregarMateriasProfessor(professorIndex) {
    const professorSelect = document.querySelector(`select[name="professores[${professorIndex}][professor_id]"]`);
    const materiaSelect = document.getElementById(`materia_${professorIndex}`);
    
    if (!professorSelect || !materiaSelect) return;
    
    const selectedOption = professorSelect.options[professorSelect.selectedIndex];
    materiaSelect.innerHTML = '<option value="">Selecione a matéria</option>';
    
    if (!selectedOption.value) {
        return;
    }
    
    const materiasJson = selectedOption.getAttribute('data-materias');
    if (!materiasJson) {
        return;
    }
    
    try {
        let materiasProfessor = [];
        
        // Tenta parsear o JSON
        if (typeof materiasJson === 'string') {
            materiasProfessor = JSON.parse(materiasJson);
        } else if (Array.isArray(materiasJson)) {
            materiasProfessor = materiasJson;
        }
        
        console.log('Matérias do professor (parseado):', materiasProfessor);
        console.log('Todas as matérias disponíveis:', materias);
        
        // Filtra matérias do professor (igual ao criar prova)
        const materiasFiltradas = materias.filter(m => 
            materiasProfessor.includes(m.nome)
        );
        
        console.log('Matérias filtradas:', materiasFiltradas);
        
        materiasFiltradas.forEach(materia => {
            const option = document.createElement('option');
            option.value = materia.id;
            option.textContent = materia.nome;
            materiaSelect.appendChild(option);
        });
        
        if (materiasFiltradas.length === 0) {
            console.warn('Nenhuma matéria encontrada para este professor. Matérias do professor:', materiasProfessor);
        }
    } catch (e) {
        console.error('Erro ao carregar matérias:', e);
        console.error('JSON que causou erro:', materiasJson);
    }
}

// Adiciona o primeiro professor automaticamente ao carregar
document.addEventListener('DOMContentLoaded', function() {
    adicionarProfessor();
});

function salvarModelo(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Coleta professores
    const professores = [];
    const professorDivs = document.querySelectorAll('[id^="professor_"]');
    
    if (professorDivs.length === 0) {
        alert('Adicione pelo menos um professor');
        return;
    }
    
    professorDivs.forEach(div => {
        const professorId = div.querySelector('select[name*="[professor_id]"]')?.value;
        const materiaId = div.querySelector('select[name*="[materia_id]"]')?.value;
        const numeroQuestoes = div.querySelector('input[name*="[numero_questoes]"]')?.value;
        
        if (!professorId || !materiaId) {
            alert('Preencha professor e matéria para todos os professores adicionados');
            return;
        }
        
        if (!numeroQuestoes || parseInt(numeroQuestoes) < 1) {
            alert('Defina o número de questões para todos os professores');
            return;
        }
        
        professores.push({
            professor_id: parseInt(professorId),
            materia_id: parseInt(materiaId),
            numero_questoes: parseInt(numeroQuestoes)
        });
    });
    
    const data = {
        nome: formData.get('nome'),
        descricao: formData.get('descricao') || null,
        professores: professores
    };
    
    fetch('<?= URL ?>/admin/blocos-modelo', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bloco modelo criado com sucesso!');
            window.location.href = '<?= URL ?>/admin/blocos-modelo';
        } else {
            alert('Erro: ' + (data.error || 'Erro ao criar bloco modelo'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}
</script>

