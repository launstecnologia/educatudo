<?php
$layout = 'professor';
$title = $title ?? 'Editar Agente de IA - EducaTudo';
$config = $config ?? [];
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Editar Agente de IA</h2>
    <p class="text-gray-600">Atualize as configurações do seu agente</p>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="formEditarAgente">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div class="space-y-8">
            <!-- Nome do Agente -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Nome do Agente *
                </label>
                <input type="text" name="nome" required value="<?= htmlspecialchars($agente['nome']) ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Propósito do Agente -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Propósito do Agente
                </label>
                <textarea name="descricao" rows="2"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($agente['descricao'] ?? '') ?></textarea>
            </div>
            
            <!-- Estilo de Linguagem -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Estilo de Linguagem
                </label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="cards-linguagem">
                    <?php
                    $linguagens = ['ludico', 'didatico', 'conversacional', 'tecnico', 'descontraido', 'formal'];
                    $labels = [
                        'ludico' => ['Lúdico', 'Linguagem divertida e envolvente'],
                        'didatico' => ['Didático', 'Foco em ensino e aprendizado'],
                        'conversacional' => ['Conversacional', 'Tom natural e descontraído'],
                        'tecnico' => ['Técnico', 'Linguagem precisa e especializada'],
                        'descontraido' => ['Descontraído', 'Tom leve e acessível'],
                        'formal' => ['Formal', 'Linguagem respeitosa e estruturada']
                    ];
                    foreach ($linguagens as $val): 
                        $checked = in_array($val, $config['linguagem'] ?? []) ? 'checked' : '';
                    ?>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[linguagem][]" value="<?= $val ?>" class="hidden" <?= $checked ?>>
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all <?= $checked ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <div class="font-medium text-gray-900"><?= $labels[$val][0] ?></div>
                            <div class="text-sm text-gray-600 mt-1"><?= $labels[$val][1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Forma de Explicar -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Forma de Explicar
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="cards-explicacao">
                    <?php
                    $explicacoes = ['respostas_rapidas', 'passo_a_passo', 'explicacao_completa', 'uso_de_exemplos', 'analogias_do_cotidiano'];
                    $labelsExp = [
                        'respostas_rapidas' => ['Respostas Rápidas', 'Objetivo e direto ao ponto'],
                        'passo_a_passo' => ['Passo a Passo', 'Explicações detalhadas e sequenciais'],
                        'explicacao_completa' => ['Explicação Completa', 'Conteúdo abrangente e detalhado'],
                        'uso_de_exemplos' => ['Uso de Exemplos', 'Ilustra com exemplos práticos'],
                        'analogias_do_cotidiano' => ['Analogias do Cotidiano', 'Compara com situações do dia a dia']
                    ];
                    foreach ($explicacoes as $val): 
                        $checked = in_array($val, $config['explicacao'] ?? []) ? 'checked' : '';
                    ?>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[explicacao][]" value="<?= $val ?>" class="hidden" <?= $checked ?>>
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all <?= $checked ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <div class="font-medium text-gray-900"><?= $labelsExp[$val][0] ?></div>
                            <div class="text-sm text-gray-600 mt-1"><?= $labelsExp[$val][1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Metodologia -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Metodologia
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="cards-metodologia">
                    <?php
                    $metodologias = ['metodo_socratico', 'perguntas_antes_da_resposta', 'exercicios_guiados', 'reforco_positivo'];
                    $labelsMet = [
                        'metodo_socratico' => ['Método Socrático', 'Perguntas que guiam o raciocínio'],
                        'perguntas_antes_da_resposta' => ['Perguntas Antes da Resposta', 'Questiona antes de explicar'],
                        'exercicios_guiados' => ['Exercícios Guiados', 'Proporciona prática orientada'],
                        'reforco_positivo' => ['Reforço Positivo', 'Encoraja e elogia o aluno']
                    ];
                    foreach ($metodologias as $val): 
                        $checked = in_array($val, $config['metodologia'] ?? []) ? 'checked' : '';
                    ?>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[metodologia][]" value="<?= $val ?>" class="hidden" <?= $checked ?>>
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all <?= $checked ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <div class="font-medium text-gray-900"><?= $labelsMet[$val][0] ?></div>
                            <div class="text-sm text-gray-600 mt-1"><?= $labelsMet[$val][1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Postura Emocional -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Postura Emocional
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="cards-postura">
                    <?php
                    $posturas = ['empatico', 'paciente', 'motivador', 'inspirador'];
                    $labelsPos = [
                        'empatico' => ['Empático', 'Compreensivo e acolhedor'],
                        'paciente' => ['Paciente', 'Tolerante e persistente'],
                        'motivador' => ['Motivador', 'Incentiva e estimula'],
                        'inspirador' => ['Inspirador', 'Estimula a curiosidade']
                    ];
                    foreach ($posturas as $val): 
                        $checked = in_array($val, $config['postura'] ?? []) ? 'checked' : '';
                    ?>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[postura][]" value="<?= $val ?>" class="hidden" <?= $checked ?>>
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all <?= $checked ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <div class="font-medium text-gray-900"><?= $labelsPos[$val][0] ?></div>
                            <div class="text-sm text-gray-600 mt-1"><?= $labelsPos[$val][1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Regras do Agente -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Regras do Agente
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="cards-regras">
                    <?php
                    $regras = ['usar_apenas_material_fornecido', 'avisar_quando_nao_souber', 'nao_inventar_conteudo', 'manter_nivel_do_aluno', 'nao_fugir_do_tema'];
                    $labelsReg = [
                        'usar_apenas_material_fornecido' => ['Usar Apenas Material Fornecido', 'Baseia-se somente nos documentos'],
                        'avisar_quando_nao_souber' => ['Avisar Quando Não Souber', 'Admite quando não tem a informação'],
                        'nao_inventar_conteudo' => ['Não Inventar Conteúdo', 'Nunca cria informações falsas'],
                        'manter_nivel_do_aluno' => ['Manter Nível do Aluno', 'Adapta ao nível do estudante'],
                        'nao_fugir_do_tema' => ['Não Fugir do Tema', 'Mantém foco na pergunta']
                    ];
                    foreach ($regras as $val): 
                        $checked = in_array($val, $config['regras'] ?? []) ? 'checked' : '';
                    ?>
                    <label class="card-option cursor-pointer">
                        <input type="checkbox" name="config[regras][]" value="<?= $val ?>" class="hidden" <?= $checked ?>>
                        <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-blue-400 hover:bg-blue-50 transition-all <?= $checked ? 'border-blue-500 bg-blue-50' : '' ?>">
                            <div class="font-medium text-gray-900"><?= $labelsReg[$val][0] ?></div>
                            <div class="text-sm text-gray-600 mt-1"><?= $labelsReg[$val][1] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="ativo" value="1" <?= $agente['ativo'] ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-semibold text-gray-700">Agente Ativo</span>
                </label>
            </div>
            
            <div class="flex justify-end space-x-4 pt-4 border-t">
                <a href="<?= URL ?>/professor/ai-agents" 
                   class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Salvar Alterações
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
    document.getElementById('formEditarAgente').addEventListener('submit', async function(e) {
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
            ativo: formData.get('ativo') === '1' ? 1 : 0,
            _token: formData.get('_token')
        };
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Salvando...';
        
        try {
            const response = await fetch('<?= URL ?>/professor/ai-agents/<?= $agente['id'] ?>/atualizar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.location.href = '<?= URL ?>/professor/ai-agents/<?= $agente['id'] ?>';
            } else {
                alert('Erro: ' + (result.error || 'Erro ao atualizar agente'));
                btn.disabled = false;
                btn.textContent = 'Salvar Alterações';
            }
        } catch (error) {
            alert('Erro de conexão');
            btn.disabled = false;
            btn.textContent = 'Salvar Alterações';
        }
    });
});
</script>
