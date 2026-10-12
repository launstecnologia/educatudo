<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Nova Prova 📝
            </h2>
            <p class="text-gray-600">
                Preencha os dados da sua prova online
            </p>
        </div>
        <a href="<?= URL ?>/professor/provas" 
           class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<?php if (!empty($evento)): ?>
<!-- Evento Info -->
<div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-3">📅 Criando Prova no Evento</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <span class="text-blue-700 font-medium">Evento:</span>
            <span class="text-blue-900 ml-2"><?= htmlspecialchars($evento['titulo']) ?></span>
        </div>
        <div>
            <span class="text-blue-700 font-medium">Data/Hora:</span>
            <span class="text-blue-900 ml-2">
                <?= date('d/m/Y', strtotime($evento['data_prova'])) ?> 
                das <?= date('H:i', strtotime($evento['hora_inicio'])) ?> 
                às <?= date('H:i', strtotime($evento['hora_fim'])) ?>
            </span>
        </div>
        <div>
            <span class="text-blue-700 font-medium">Tipo:</span>
            <span class="text-blue-900 ml-2">
                <?= $evento['tipo_prova'] === 'substitutiva' ? 'Substitutiva' : 'Original' ?>
            </span>
        </div>
        <div>
            <span class="text-blue-700 font-medium">Configuração de Nota:</span>
            <span class="text-blue-900 ml-2">
                <?= $evento['configuracao_nota'] === 'professor_por_questao' ? 'Você coloca por questão' : 'Coordenação calcula' ?>
            </span>
        </div>
        <?php if (!empty($turmas)): ?>
        <div class="md:col-span-2">
            <span class="text-blue-700 font-medium">Turma(s):</span>
            <span class="text-blue-900 ml-2">
                <?= implode(', ', array_column($turmas, 'nome')) ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($materiaEvento)): ?>
        <div>
            <span class="text-blue-700 font-medium">Matéria:</span>
            <span class="text-blue-900 ml-2"><?= htmlspecialchars($materiaEvento['nome']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Form Section -->
<?php if (!empty($evento)): ?>
<!-- Quando há evento, cria a prova automaticamente e vai direto para questões -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-blue-200 p-6">
    <div class="text-center py-8">
        <div class="mb-4">
            <svg class="mx-auto h-16 w-16 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Criando sua prova...</h3>
        <p class="text-gray-600">Você será redirecionado para adicionar questões em instantes.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cria a prova automaticamente quando há evento
    // Pega materia_id da URL ou da variável PHP
    const urlParams = new URLSearchParams(window.location.search);
    const materiaIdFromUrl = urlParams.get('materia_id');
    const materiaId = <?= !empty($materiaEvento['id']) ? $materiaEvento['id'] : 'null' ?> || materiaIdFromUrl;
    
    if (!materiaId) {
        console.error('Erro: materia_id não encontrado');
        alert('Erro: Matéria não encontrada para este evento. Por favor, tente novamente.');
        window.location.href = '<?= URL ?>/professor/provas';
        return;
    }
    
    const data = {
        evento_id: <?= $evento['id'] ?>,
        materia_id: parseInt(materiaId),
        titulo: <?= json_encode($evento['titulo'] ?? 'Prova do Evento') ?>,
        data_inicio: <?= json_encode(date('Y-m-d H:i:s', strtotime($evento['data_prova'] . ' ' . $evento['hora_inicio']))) ?>,
        data_fim: <?= json_encode(date('Y-m-d H:i:s', strtotime($evento['data_prova'] . ' ' . $evento['hora_fim']))) ?>,
        turma_id: <?= (!empty($turmas) && count($turmas) == 1) ? $turmas[0]['id'] : 'null' ?>,
        descricao: '',
        tempo_limite: null,
        valor_total: '100.00',
        liberar_resultado: <?= json_encode($evento['liberar_gabarito'] === 'imediatamente' ? 'imediatamente' : 'apos_todos') ?>,
        permite_correcao: '1'
    };
    
    fetch('<?= URL ?>/professor/provas/salvar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= URL ?>/professor/provas/editar/' + data.id;
        } else {
            alert('Erro: ' + (data.error || 'Erro ao criar prova'));
            if (data.errors) {
                console.error('Erros:', data.errors);
                let errorMsg = 'Erro: ' + (data.error || 'Erro ao criar prova');
                if (data.errors && Object.keys(data.errors).length > 0) {
                    errorMsg += '\n\nDetalhes:\n';
                    for (let field in data.errors) {
                        errorMsg += '- ' + data.errors[field] + '\n';
                    }
                }
                alert(errorMsg);
            }
            window.location.href = '<?= URL ?>/professor/provas';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar prova');
        window.location.href = '<?= URL ?>/professor/provas';
    });
});
</script>
<?php else: ?>
<!-- Formulário normal quando NÃO há evento -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-blue-200">
    <form id="provaForm" class="p-6 space-y-6">
        <!-- Informações Básicas -->
        <div class="border-b border-gray-200 pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações Básicas</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (empty($evento)): ?>
                <!-- Campos mostrados apenas quando NÃO está criando dentro de um evento -->
                <div>
                    <label for="materia_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Matéria <span class="text-red-500">*</span>
                    </label>
                    <select id="materia_id" name="materia_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione uma matéria</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= htmlspecialchars($materia['id']) ?>">
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="data_inicio" class="block text-sm font-semibold text-gray-700 mb-2">
                        Data/Hora de Início <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="data_inicio" name="data_inicio" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="data_fim" class="block text-sm font-semibold text-gray-700 mb-2">
                        Data/Hora de Término <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="data_fim" name="data_fim" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="turma_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Turma (opcional - deixe vazio para múltiplas turmas)
                    </label>
                    <select id="turma_id" name="turma_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione uma turma (ou deixe vazio)</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option value="<?= htmlspecialchars($turma['id']) ?>">
                                <?= htmlspecialchars($turma['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <!-- Campos ocultos quando está criando dentro de um evento (valores vêm do evento) -->
                <input type="hidden" id="materia_id" name="materia_id" value="<?= $materiaEvento['id'] ?? '' ?>">
                <input type="hidden" id="data_inicio" name="data_inicio" value="<?= date('Y-m-d\TH:i', strtotime($evento['data_prova'] . ' ' . $evento['hora_inicio'])) ?>">
                <input type="hidden" id="data_fim" name="data_fim" value="<?= date('Y-m-d\TH:i', strtotime($evento['data_prova'] . ' ' . $evento['hora_fim'])) ?>">
                <?php if (!empty($turmas) && count($turmas) == 1): ?>
                    <input type="hidden" id="turma_id" name="turma_id" value="<?= $turmas[0]['id'] ?>">
                <?php else: ?>
                    <input type="hidden" id="turma_id" name="turma_id" value="">
                <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($evento)): ?>
                <!-- Campos mostrados apenas quando NÃO está criando dentro de um evento -->
                <div>
                    <label for="titulo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Título <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="titulo" name="titulo" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Título da prova">
                </div>

                <div>
                    <label for="tempo_limite" class="block text-sm font-semibold text-gray-700 mb-2">
                        Tempo Limite (minutos) - Opcional
                    </label>
                    <input type="number" id="tempo_limite" name="tempo_limite" min="1"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Deixe vazio para sem limite">
                </div>

                <div>
                    <label for="valor_total" class="block text-sm font-semibold text-gray-700 mb-2">
                        Valor Total da Prova
                    </label>
                    <input type="number" id="valor_total" name="valor_total" step="0.01" value="100.00"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="liberar_resultado" class="block text-sm font-semibold text-gray-700 mb-2">
                        Quando Liberar Resultado
                    </label>
                    <select id="liberar_resultado" name="liberar_resultado"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="imediatamente">Imediatamente após finalizar</option>
                        <option value="apos_todos">Após todos terminarem</option>
                        <option value="nao_liberar">Não liberar</option>
                    </select>
                </div>
                <?php else: ?>
                <!-- Campos ocultos quando está criando dentro de um evento -->
                <input type="hidden" id="titulo" name="titulo" value="<?= htmlspecialchars($evento['titulo'] ?? 'Prova do Evento') ?>">
                <input type="hidden" id="tempo_limite" name="tempo_limite" value="">
                <input type="hidden" id="valor_total" name="valor_total" value="100.00">
                <input type="hidden" id="liberar_resultado" name="liberar_resultado" value="<?= $evento['liberar_gabarito'] === 'imediatamente' ? 'imediatamente' : 'apos_todos' ?>">
                <?php endif; ?>
            </div>

            <?php if (empty($evento)): ?>
            <!-- Descrição e checkbox apenas quando NÃO está criando dentro de um evento -->
            <div class="mt-6">
                <label for="descricao" class="block text-sm font-semibold text-gray-700 mb-2">
                    Descrição
                </label>
                <textarea id="descricao" name="descricao" rows="3"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Descrição da prova (opcional)"></textarea>
            </div>

            <div class="mt-6 flex items-center space-x-6">
                <label class="flex items-center">
                    <input type="checkbox" id="permite_correcao" name="permite_correcao" value="1"
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Permitir correção manual de questões dissertativas</span>
                </label>
            </div>
            <?php else: ?>
            <!-- Campos ocultos quando está criando dentro de um evento -->
            <input type="hidden" id="descricao" name="descricao" value="">
            <input type="hidden" id="permite_correcao" name="permite_correcao" value="1">
            <?php endif; ?>
        </div>

        <!-- Botões -->
        <div class="flex justify-end space-x-4 pt-6">
            <a href="<?= URL ?>/professor/provas" 
               class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Criar Prova
            </button>
        </div>
    </form>
</div>

<script>
<?php if (!empty($evento)): ?>
// Quando está criando dentro de um evento, os campos já estão preenchidos via hidden inputs
// Não precisa de JavaScript adicional
<?php endif; ?>

document.getElementById('provaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (key === 'permite_correcao') {
            data[key] = formData.has(key) ? 1 : 0;
        } else {
            data[key] = value;
        }
    }
    
    // Inclui evento_id se estiver presente
    const eventoIdInput = document.getElementById('evento_id');
    if (eventoIdInput && eventoIdInput.value) {
        data.evento_id = eventoIdInput.value;
    }
    
    // Converte turma_id vazio para null
    if (!data.turma_id) {
        data.turma_id = null;
    }
    
    fetch('<?= URL ?>/professor/provas/salvar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Se veio de um evento, já redireciona para edição (onde pode adicionar questões)
            // Se não, também redireciona para edição
            window.location.href = '<?= URL ?>/professor/provas/editar/' + data.id;
        } else {
            alert('Erro: ' + (data.error || 'Erro ao criar prova'));
            if (data.errors) {
                console.error('Erros:', data.errors);
                // Mostra erros específicos se houver
                let errorMsg = 'Erro: ' + (data.error || 'Erro ao criar prova');
                if (data.errors && Object.keys(data.errors).length > 0) {
                    errorMsg += '\n\nDetalhes:\n';
                    for (let field in data.errors) {
                        errorMsg += '- ' + data.errors[field] + '\n';
                    }
                }
                alert(errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar prova');
    });
});
</script>
<?php endif; ?>

