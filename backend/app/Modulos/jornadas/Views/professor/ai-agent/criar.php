<?php
$layout = 'professor';
$title = $title ?? 'Criar Agente de IA - EducaTudo';
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Criar Novo Agente de IA</h2>
    <p class="text-gray-600">Configure seu agente selecionando as características desejadas</p>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="formCriarAgente">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div class="space-y-8">
            <!-- Nome do Agente -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Nome do Agente *
                </label>
                <input type="text" name="nome" id="nomeAgente" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Ex: Assistente de Matemática">
            </div>
            
            <!-- Propósito do Agente -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Propósito do Agente
                </label>
                <textarea name="descricao" rows="2"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Descreva o propósito deste agente..."></textarea>
            </div>
            
            <!-- Estilo de Linguagem -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Estilo de Linguagem
                </label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="cards-linguagem">
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="ludico" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Lúdico</div>
                            <div class="text-sm text-gray-600 mt-1">Linguagem divertida e envolvente</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="didatico" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Didático</div>
                            <div class="text-sm text-gray-600 mt-1">Foco em ensino e aprendizado</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="conversacional" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Conversacional</div>
                            <div class="text-sm text-gray-600 mt-1">Tom natural e descontraído</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="tecnico" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Técnico</div>
                            <div class="text-sm text-gray-600 mt-1">Linguagem precisa e especializada</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="descontraido" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Descontraído</div>
                            <div class="text-sm text-gray-600 mt-1">Tom leve e acessível</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="formal" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Formal</div>
                            <div class="text-sm text-gray-600 mt-1">Linguagem respeitosa e estruturada</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Forma de Explicar -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Forma de Explicar
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="cards-explicacao">
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[explicacao][]" value="respostas_rapidas" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Respostas Rápidas</div>
                            <div class="text-sm text-gray-600 mt-1">Objetivo e direto ao ponto</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[explicacao][]" value="passo_a_passo" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Passo a Passo</div>
                            <div class="text-sm text-gray-600 mt-1">Explicações detalhadas e sequenciais</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[explicacao][]" value="explicacao_completa" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Explicação Completa</div>
                            <div class="text-sm text-gray-600 mt-1">Conteúdo abrangente e detalhado</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[explicacao][]" value="uso_de_exemplos" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Uso de Exemplos</div>
                            <div class="text-sm text-gray-600 mt-1">Ilustra com exemplos práticos</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[explicacao][]" value="analogias_do_cotidiano" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Analogias do Cotidiano</div>
                            <div class="text-sm text-gray-600 mt-1">Compara com situações do dia a dia</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Metodologia -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Metodologia
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="cards-metodologia">
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[metodologia][]" value="metodo_socratico" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Método Socrático</div>
                            <div class="text-sm text-gray-600 mt-1">Perguntas que guiam o raciocínio</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[metodologia][]" value="perguntas_antes_da_resposta" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Perguntas Antes da Resposta</div>
                            <div class="text-sm text-gray-600 mt-1">Questiona antes de explicar</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[metodologia][]" value="exercicios_guiados" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Exercícios Guiados</div>
                            <div class="text-sm text-gray-600 mt-1">Proporciona prática orientada</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[metodologia][]" value="reforco_positivo" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Reforço Positivo</div>
                            <div class="text-sm text-gray-600 mt-1">Encoraja e elogia o aluno</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Postura Emocional -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Postura Emocional
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="cards-postura">
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[postura][]" value="empatico" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Empático</div>
                            <div class="text-sm text-gray-600 mt-1">Compreensivo e acolhedor</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[postura][]" value="paciente" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Paciente</div>
                            <div class="text-sm text-gray-600 mt-1">Tolerante e persistente</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[postura][]" value="motivador" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Motivador</div>
                            <div class="text-sm text-gray-600 mt-1">Incentiva e estimula</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[postura][]" value="inspirador" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Inspirador</div>
                            <div class="text-sm text-gray-600 mt-1">Estimula a curiosidade</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Regras do Agente -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Regras do Agente
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="cards-regras">
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[regras][]" value="usar_apenas_material_fornecido" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Usar Apenas Material Fornecido</div>
                            <div class="text-sm text-gray-600 mt-1">Baseia-se somente nos documentos</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[regras][]" value="avisar_quando_nao_souber" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Avisar Quando Não Souber</div>
                            <div class="text-sm text-gray-600 mt-1">Admite quando não tem a informação</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[regras][]" value="nao_inventar_conteudo" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Não Inventar Conteúdo</div>
                            <div class="text-sm text-gray-600 mt-1">Nunca cria informações falsas</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[regras][]" value="manter_nivel_do_aluno" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Manter Nível do Aluno</div>
                            <div class="text-sm text-gray-600 mt-1">Adapta ao nível do estudante</div>
                        </div>
                    </label>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[regras][]" value="nao_fugir_do_tema" class="hidden">
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all">
                            <div class="font-medium text-gray-900">Não Fugir do Tema</div>
                            <div class="text-sm text-gray-600 mt-1">Mantém foco na pergunta</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 pt-4 border-t">
                <a href="<?= URL ?>/professor/ai-agents" 
                   class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Criar Agente
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.card-option input:checked + div {
    border-color: #3b82f6;
    background-color: #eff6ff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.card-option input:checked + div .font-medium {
    color: #1e40af;
    font-weight: 600;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle visual dos cards - permite múltipla seleção
    document.querySelectorAll('.card-option').forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        const cardDiv = card.querySelector('div');
        
        if (!checkbox || !cardDiv) return;
        
        // Atualiza visual quando checkbox muda
        function updateVisual() {
            if (checkbox.checked) {
                cardDiv.classList.add('border-blue-500', 'bg-blue-50');
                cardDiv.classList.remove('border-gray-200');
            } else {
                cardDiv.classList.remove('border-blue-500', 'bg-blue-50');
                cardDiv.classList.add('border-gray-200');
            }
        }
        
        // Inicializa visual
        updateVisual();
        
        // Quando checkbox muda (por qualquer motivo)
        checkbox.addEventListener('change', function() {
            updateVisual();
        });
        
        // Quando clica no card
        card.addEventListener('click', function(e) {
            // Se clicou diretamente no checkbox, deixa o comportamento padrão do label funcionar
            if (e.target === checkbox) {
                // O label já vai fazer o toggle, só atualiza visual
                setTimeout(updateVisual, 10);
                return;
            }
            
            // Previne o comportamento padrão do label para evitar duplo toggle
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle manual do checkbox
            checkbox.checked = !checkbox.checked;
            
            // Atualiza visual imediatamente
            updateVisual();
        });
    });
    
    // Submit do formulário
    document.getElementById('formCriarAgente').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {
            nome: formData.get('nome'),
            descricao: formData.get('descricao') || '',
            config: {
                linguagem: formData.getAll('config[linguagem][]'),
                explicacao: formData.getAll('config[explicacao][]'),
                metodologia: formData.getAll('config[metodologia][]'),
                postura: formData.getAll('config[postura][]'),
                regras: formData.getAll('config[regras][]')
            },
            _token: formData.get('_token')
        };
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Criando...';
        
        try {
            const response = await fetch('<?= URL ?>/professor/ai-agents/salvar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.location.href = '<?= URL ?>/professor/ai-agents/' + result.agente_id;
            } else {
                alert('Erro: ' + (result.error || 'Erro ao criar agente'));
                btn.disabled = false;
                btn.textContent = 'Criar Agente';
            }
        } catch (error) {
            alert('Erro de conexão');
            btn.disabled = false;
            btn.textContent = 'Criar Agente';
        }
    });
});
</script>
