<?php
/**
 * Editar Bloco Modelo
 * Acesso: Coordenação
 */
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Editar Bloco Modelo 📋
            </h2>
            <p class="text-gray-600">
                Edite o template reutilizável
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
                   value="<?= htmlspecialchars($modelo['nome']) ?>"
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
                      placeholder="Descrição opcional do modelo"><?= htmlspecialchars($modelo['descricao'] ?? '') ?></textarea>
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
                Salvar Alterações
            </button>
        </div>
    </form>
</div>

<script>
const professores = <?= json_encode($professores ?? []) ?>;
const materias = <?= json_encode($materias ?? []) ?>;
const modeloProfessores = <?= json_encode($modelo['professores'] ?? []) ?>;
let professorCounter = 0;

// Debug: verifica os dados do modelo
console.log('Modelo Professores:', modeloProfessores);
console.log('Professores disponíveis:', professores);
console.log('Matérias disponíveis:', materias);

function adicionarProfessor(profModelo = null) {
    professorCounter++;
    const container = document.getElementById('professoresContainer');
    
    const professorDiv = document.createElement('div');
    professorDiv.className = 'border border-gray-300 rounded-lg p-4 bg-gray-50';
    professorDiv.id = `professor_${professorCounter}`;
    
    const professorId = profModelo ? (profModelo.professor_id || profModelo.professor_id) : '';
    // Garante que materia_id seja um número ou string válida
    const materiaId = profModelo ? (profModelo.materia_id ? String(profModelo.materia_id) : '') : '';
    const numeroQuestoes = profModelo ? (profModelo.numero_questoes || 5) : 5;
    
    console.log(`Adicionando professor ${professorCounter}:`, {
        professorId,
        materiaId,
        numeroQuestoes,
        profModelo
    });
    
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
                        id="professor_select_${professorCounter}"
                        required
                        data-materia-id="${materiaId || ''}"
                        onchange="carregarMateriasProfessor(${professorCounter})"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Selecione o professor</option>
                    ${professores.map(p => `
                        <option value="${p.id}" 
                                data-materias='${JSON.stringify((p.materias || []).map(m => m.nome || m))}'
                                ${p.id == professorId ? 'selected' : ''}>
                            ${p.nome}
                        </option>
                    `).join('')}
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
                       value="${numeroQuestoes}"
                       required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>
    `;
    
    container.appendChild(professorDiv);
    
    // Se há um professor selecionado, carrega as matérias e seleciona a matéria correta
    if (professorId && materiaId) {
        // Aguarda o DOM ser atualizado e garante que o select esteja pronto
        const tentarCarregar = (tentativas = 0) => {
            const profSelect = document.getElementById(`professor_select_${professorCounter}`);
            const materiaSelect = document.getElementById(`materia_${professorCounter}`);
            
            if (profSelect && materiaSelect && profSelect.value == professorId) {
                carregarMateriasProfessor(professorCounter, materiaId);
            } else if (tentativas < 5) {
                // Tenta novamente após um delay
                setTimeout(() => tentarCarregar(tentativas + 1), 100);
            }
        };
        
        requestAnimationFrame(() => {
            setTimeout(() => tentarCarregar(), 50);
        });
    }
}

function removerProfessor(id) {
    const professorDiv = document.getElementById(`professor_${id}`);
    if (professorDiv) {
        professorDiv.remove();
    }
}

function carregarMateriasProfessor(professorIndex, materiaIdParaSelecionar = null) {
    const professorSelect = document.getElementById(`professor_select_${professorIndex}`) || 
                            document.querySelector(`select[name="professores[${professorIndex}][professor_id]"]`);
    const materiaSelect = document.getElementById(`materia_${professorIndex}`);
    
    if (!professorSelect || !materiaSelect) {
        console.warn(`Elementos não encontrados para professor ${professorIndex}`);
        return;
    }
    
    const selectedOption = professorSelect.options[professorSelect.selectedIndex];
    materiaSelect.innerHTML = '<option value="">Selecione a matéria</option>';
    
    if (!selectedOption || !selectedOption.value) {
        console.warn(`Nenhum professor selecionado para índice ${professorIndex}`);
        return;
    }
    
    const materiasJson = selectedOption.getAttribute('data-materias');
    if (!materiasJson) {
        console.warn(`Nenhuma matéria encontrada para professor ${professorIndex}`);
        return;
    }
    
    try {
        const materiasProfessor = JSON.parse(materiasJson);
        
        // Filtra matérias do professor
        const materiasFiltradas = materias.filter(m => 
            materiasProfessor.includes(m.nome)
        );
        
        if (materiasFiltradas.length === 0) {
            console.warn(`Nenhuma matéria filtrada para professor ${professorIndex}. Matérias do professor:`, materiasProfessor);
        }
        
        materiasFiltradas.forEach(materia => {
            const option = document.createElement('option');
            option.value = materia.id;
            option.textContent = materia.nome;
            materiaSelect.appendChild(option);
        });
        
        // Seleciona a matéria se foi especificada (para edição)
        // Usa o parâmetro primeiro, depois tenta o atributo
        let materiaIdFinal = materiaIdParaSelecionar ? String(materiaIdParaSelecionar) : null;
        if (!materiaIdFinal) {
            materiaIdFinal = professorSelect.getAttribute('data-materia-id');
        }
        
        if (materiaIdFinal) {
            // Função para tentar selecionar a matéria (com retry)
            const tentarSelecionarMateria = (tentativas = 0) => {
                const option = materiaSelect.querySelector(`option[value="${materiaIdFinal}"]`);
                if (option) {
                    materiaSelect.value = materiaIdFinal;
                    console.log(`✓ Matéria ${materiaIdFinal} selecionada com sucesso para professor ${professorIndex}`);
                    return true;
                } else {
                    // Tenta encontrar por comparação numérica também
                    const optionByNum = Array.from(materiaSelect.options).find(opt => 
                        opt.value && (parseInt(opt.value) === parseInt(materiaIdFinal))
                    );
                    if (optionByNum) {
                        materiaSelect.value = optionByNum.value;
                        console.log(`✓ Matéria ${materiaIdFinal} selecionada por comparação numérica para professor ${professorIndex}`);
                        return true;
                    } else if (tentativas < 10) {
                        // Tenta novamente após um delay
                        setTimeout(() => tentarSelecionarMateria(tentativas + 1), 100);
                        return false;
                    } else {
                        console.warn(`✗ Matéria ID ${materiaIdFinal} não encontrada após ${tentativas} tentativas para professor ${professorIndex}. Opções disponíveis:`, 
                            Array.from(materiaSelect.options).map(opt => ({ value: opt.value, text: opt.text })));
                        return false;
                    }
                }
            };
            
            // Inicia a tentativa de seleção
            setTimeout(() => tentarSelecionarMateria(), 50);
        }
    } catch (e) {
        console.error('Erro ao carregar matérias:', e, 'JSON:', materiasJson);
    }
}

// Carrega professores do modelo ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    if (modeloProfessores.length > 0) {
        // Adiciona professores um por vez com delay para garantir que cada um seja processado corretamente
        modeloProfessores.forEach((prof, index) => {
            setTimeout(() => {
                adicionarProfessor(prof);
            }, index * 250); // Delay de 250ms entre cada professor
        });
    } else {
        adicionarProfessor();
    }
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
    
    fetch('<?= URL ?>/admin/blocos-modelo/<?= $modelo['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bloco modelo atualizado com sucesso!');
            window.location.href = '<?= URL ?>/admin/blocos-modelo';
        } else {
            alert('Erro: ' + (data.error || 'Erro ao atualizar bloco modelo'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}
</script>

