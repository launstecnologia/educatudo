<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <a href="<?= URL ?>/exercicios-personalizados" class="text-purple-600 hover:text-purple-800 flex items-center mb-4">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Voltar
        </a>
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Minhas Listas de Exercícios</h1>
        <p class="text-lg text-gray-600">Gerencie e faça seus exercícios personalizados</p>
    </div>

    <div class="mb-6">
        <a href="<?= URL ?>/exercicios-personalizados/criar" class="btn-ai-primary inline-flex items-center px-6 py-3 rounded-xl font-semibold transition-all duration-300">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Criar Nova Lista
        </a>
    </div>

    <?php if (empty($listas)): ?>
        <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
            <svg class="mx-auto h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-4 text-xl font-semibold text-gray-900">Nenhuma lista criada ainda</h3>
            <p class="mt-2 text-gray-600">Comece criando sua primeira lista de exercícios personalizados</p>
            <a href="<?= URL ?>/exercicios-personalizados/criar" class="btn-ai-primary mt-6 inline-flex items-center px-6 py-3 rounded-xl font-semibold transition-colors">
                Criar Primeira Lista
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($listas as $lista): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($lista['titulo']) ?></h3>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($lista['materia']) ?></p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $lista['status'] === 'concluido' ? 'bg-green-100 text-green-800' : ($lista['status'] === 'gerando' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?php
                            if ($lista['status'] === 'concluido') echo '✅ Pronta';
                            elseif ($lista['status'] === 'gerando') echo '⏳ Gerando';
                            else echo '❌ Erro';
                            ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                        <span>📚 <?= htmlspecialchars($lista['tema']) ?></span>
                        <span>📊 <?= $lista['total_questoes'] ?> questões</span>
                    </div>

                    <?php if ($lista['status'] === 'concluido'): ?>
                        <div class="space-y-2">
                            <button onclick="iniciarExercicio(<?= $lista['id'] ?>); return false;" class="w-full bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700 transition-colors font-semibold">
                                🚀 Iniciar Exercício
                            </button>
                            <?php if ($lista['total_sessoes'] > 0): ?>
                            <a href="<?= URL ?>/exercicios-personalizados/historico?lista_id=<?= $lista['id'] ?>" 
                               class="block w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-200 transition-colors font-semibold text-center text-sm">
                                📚 Ver Histórico
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($lista['status'] === 'gerando'): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-center text-sm">
                                <svg class="animate-spin h-4 w-4 text-yellow-600 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-yellow-800">Gerando...</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-xs text-red-800"><?= htmlspecialchars($lista['mensagem_erro'] ?? 'Erro desconhecido') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function mostrarErro(mensagem) {
    // Remove qualquer modal de erro existente
    const erroExistente = document.getElementById('erroModal');
    if (erroExistente) {
        erroExistente.remove();
    }
    
    // Cria modal de erro
    const modalErro = document.createElement('div');
    modalErro.id = 'erroModal';
    modalErro.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modalErro.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-2xl font-bold text-red-600">Erro</h3>
                <button onclick="document.getElementById('erroModal').remove()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mb-6">
                <p class="text-gray-700 whitespace-pre-wrap break-words">${mensagem}</p>
            </div>
            <button onclick="document.getElementById('erroModal').remove()" class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors font-semibold">
                Fechar
            </button>
        </div>
    `;
    
    document.body.appendChild(modalErro);
    
    // Fecha ao clicar fora
    modalErro.addEventListener('click', function(e) {
        if (e.target === modalErro) {
            modalErro.remove();
        }
    });
}

function iniciarExercicio(listaId) {
    console.log('iniciarExercicio chamado com listaId:', listaId);
    
    if (!confirm('Deseja iniciar esta lista de exercícios?')) {
        return false;
    }
    
    // Desabilita o botão para evitar cliques múltiplos
    const button = event.target.closest('button');
    if (button) {
        button.disabled = true;
        button.textContent = 'Iniciando...';
    }
    
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    formData.append('lista_id', listaId);
    
    console.log('Enviando requisição para:', '<?= URL ?>/exercicios-personalizados/iniciar');
    
    fetch('<?= URL ?>/exercicios-personalizados/iniciar', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        console.log('Resposta recebida. Status:', response.status);
        const text = await response.text();
        console.log('Texto da resposta:', text.substring(0, 500));
        
        // Verifica se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Resposta não é JSON. Content-Type:', contentType);
            console.error('Conteúdo recebido:', text);
            throw new Error('Resposta do servidor não é JSON válido. Conteúdo: ' + text.substring(0, 200));
        }
        
        // Tenta fazer parse do JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Texto recebido:', text);
            throw new Error('Erro ao decodificar JSON: ' + parseError.message + '. Resposta: ' + text.substring(0, 300));
        }
        
        return data;
    })
    .then(data => {
        console.log('Dados parseados:', data);
        if (data.success) {
            if (data.sessao_id) {
                const url = '<?= URL ?>/exercicios-personalizados/executar?sessao_id=' + data.sessao_id + '&questao=1';
                console.log('Redirecionando para:', url);
                // Usa replace ao invés de href para evitar problemas com histórico
                window.location.replace(url);
            } else {
                console.error('sessao_id não encontrado na resposta:', data);
                mostrarErro('Erro: sessao_id não encontrado na resposta do servidor');
                if (button) {
                    button.disabled = false;
                    button.textContent = '🚀 Iniciar Exercício';
                }
            }
        } else {
            mostrarErro('Erro: ' + (data.error || 'Não foi possível iniciar os exercícios'));
            if (button) {
                button.disabled = false;
                button.textContent = '🚀 Iniciar Exercício';
            }
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        mostrarErro('Erro ao iniciar exercícios: ' + error.message);
        if (button) {
            button.disabled = false;
            button.textContent = '🚀 Iniciar Exercício';
        }
    });
    
    return false; // Previne comportamento padrão do botão
}

</script>

