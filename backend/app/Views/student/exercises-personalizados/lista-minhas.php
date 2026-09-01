<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <a href="<?= URL ?>/exercicios-personalizados" class="text-gray-600 hover:text-gray-900 inline-flex items-center mb-4">
            <i class="fa-solid fa-arrow-left mr-2"></i>
            Voltar
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Minhas listas de exercícios</h1>
        <p class="text-lg text-gray-600">Gerencie e faça seus exercícios personalizados</p>
    </div>

    <div class="mb-6">
        <a href="<?= URL ?>/exercicios-personalizados/criar" class="btn-ai-primary inline-flex items-center px-6 py-3 rounded-xl font-semibold transition-all duration-300">
            <i class="fa-solid fa-plus mr-2"></i>
            Criar nova lista
        </a>
    </div>

    <?php if (empty($listas)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
            <i class="fa-regular fa-folder-open text-5xl text-gray-300"></i>
            <h3 class="mt-4 text-xl font-semibold text-gray-900">Nenhuma lista criada ainda</h3>
            <p class="mt-2 text-gray-600">Comece criando sua primeira lista de exercícios personalizados</p>
            <a href="<?= URL ?>/exercicios-personalizados/criar" class="btn-ai-primary mt-6 inline-flex items-center px-6 py-3 rounded-xl font-semibold">
                Criar primeira lista
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-stretch">
            <?php foreach ($listas as $lista): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow flex flex-col h-full min-w-0"
                     <?= $lista['status'] === 'gerando' ? 'data-lista-gerando="' . (int) $lista['id'] . '"' : '' ?>>
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-gray-900 leading-snug line-clamp-2 break-words" title="<?= htmlspecialchars($lista['titulo']) ?>">
                                <?= htmlspecialchars($lista['titulo']) ?>
                            </h3>
                            <?php if (!empty($lista['materia'])): ?>
                                <p class="text-sm text-gray-600 mt-1 truncate"><?= htmlspecialchars($lista['materia']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="flex-shrink-0 px-3 py-1 text-xs font-semibold rounded-full <?= $lista['status'] === 'concluido' ? 'bg-green-100 text-green-800' : ($lista['status'] === 'gerando' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                            <?php
                            if ($lista['status'] === 'concluido') {
                                echo '<i class="fa-solid fa-check mr-1"></i>Pronta';
                            } elseif ($lista['status'] === 'gerando') {
                                echo '<i class="fa-solid fa-clock mr-1"></i>Gerando';
                            } else {
                                echo '<i class="fa-solid fa-xmark mr-1"></i>Erro';
                            }
                            ?>
                        </span>
                    </div>

                    <div class="text-sm text-gray-600 space-y-1 mb-3">
                        <?php if (!empty($lista['tema'])): ?>
                            <p class="flex items-start gap-2 min-w-0">
                                <i class="fa-solid fa-book text-gray-400 mt-0.5 flex-shrink-0"></i>
                                <span class="line-clamp-1 break-words min-w-0" title="<?= htmlspecialchars($lista['tema']) ?>"><?= htmlspecialchars($lista['tema']) ?></span>
                            </p>
                        <?php endif; ?>
                        <p class="flex items-center gap-2">
                            <i class="fa-solid fa-list-ol text-gray-400 flex-shrink-0"></i>
                            <?= (int) $lista['total_questoes'] ?> questões
                        </p>
                    </div>
                    <p class="text-xs text-gray-400">
                        Solicitada em <?= !empty($lista['created_at']) ? date('d/m/Y \à\s H:i', strtotime($lista['created_at'])) : '—' ?>
                    </p>
                    <p class="text-xs text-gray-400 mb-4">
                        <?php if ($lista['status'] === 'concluido' && !empty($lista['updated_at'])): ?>
                            Entregue em <?= date('d/m/Y \à\s H:i', strtotime($lista['updated_at'])) ?>
                        <?php else: ?>
                            &nbsp;
                        <?php endif; ?>
                    </p>

                    <div class="mt-auto space-y-2">
                    <?php if ($lista['status'] === 'concluido'): ?>
                            <button type="button" onclick="iniciarExercicio(<?= (int) $lista['id'] ?>); return false;" class="w-full btn-ai-primary py-2 px-4 rounded-lg font-semibold inline-flex items-center justify-center">
                                <i class="fa-solid fa-play mr-2"></i>
                                Iniciar exercício
                            </button>
                            <?php if ($lista['total_sessoes'] > 0): ?>
                            <a href="<?= URL ?>/exercicios-personalizados/historico?lista_id=<?= (int) $lista['id'] ?>"
                               class="block w-full border border-gray-300 bg-white text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-50 transition-colors font-semibold text-center text-sm">
                                <i class="fa-solid fa-clock-rotate-left mr-1"></i>
                                Ver histórico
                            </a>
                            <?php endif; ?>
                    <?php elseif ($lista['status'] === 'gerando'): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-start text-sm">
                                <svg class="animate-spin h-4 w-4 text-yellow-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-yellow-800">Gerando... você pode sair desta tela, ela atualiza sozinha.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-2">
                            <p class="text-xs text-red-800 line-clamp-3" title="<?= htmlspecialchars($lista['mensagem_erro'] ?? 'erro desconhecido') ?>">Ops, não consegui gerar: <?= htmlspecialchars($lista['mensagem_erro'] ?? 'erro desconhecido') ?></p>
                        </div>
                        <button type="button" onclick="tentarNovamente(<?= (int) $lista['id'] ?>, this); return false;" class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors font-semibold text-sm inline-flex items-center justify-center">
                            <i class="fa-solid fa-rotate-right mr-2"></i>
                            Tentar novamente
                        </button>
                    <?php endif; ?>
                    </div>
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
                    button.innerHTML = '<i class="fa-solid fa-play mr-2"></i>Iniciar exercício';
                }
            }
        } else {
            mostrarErro('Erro: ' + (data.error || 'Não foi possível iniciar os exercícios'));
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-play mr-2"></i>Iniciar exercício';
            }
        }
    })
    .catch(error => {
        console.error('Erro completo:', error);
        mostrarErro('Erro ao iniciar exercícios: ' + error.message);
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-play mr-2"></i>Iniciar exercício';
        }
    });
    
    return false; // Previne comportamento padrão do botão
}

function tentarNovamente(listaId, botao) {
    if (botao) {
        botao.disabled = true;
        botao.textContent = 'Reenviando...';
    }
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    fetch('<?= URL ?>/exercicios-personalizados/tentar-novamente/' + listaId, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            mostrarErro('Erro: ' + (data.error || 'Não foi possível reenviar.'));
            if (botao) {
                botao.disabled = false;
                botao.innerHTML = '<i class="fa-solid fa-rotate-right mr-2"></i>Tentar novamente';
            }
            return;
        }
        window.location.reload();
    })
    .catch(error => {
        mostrarErro('Erro ao reenviar: ' + error.message);
        if (botao) {
            botao.disabled = false;
            botao.innerHTML = '<i class="fa-solid fa-rotate-right mr-2"></i>Tentar novamente';
        }
    });
}

// Atualização automática: enquanto houver lista "Gerando" nesta página, verifica o status
// periodicamente e recarrega só quando algo realmente mudou — o aluno não precisa apertar F5.
(function () {
    const cardsGerando = document.querySelectorAll('[data-lista-gerando]');
    if (cardsGerando.length === 0) return;

    const listaIds = Array.from(cardsGerando).map(function (el) { return el.getAttribute('data-lista-gerando'); });

    const intervalId = setInterval(function () {
        Promise.all(listaIds.map(function (id) {
            return fetch('<?= URL ?>/exercicios-personalizados/status?lista_id=' + id)
                .then(function (r) { return r.json(); })
                .then(function (data) { return data.status; })
                .catch(function () { return 'gerando'; });
        })).then(function (statuses) {
            const aindaGerando = statuses.some(function (s) { return s === 'gerando'; });
            if (!aindaGerando) {
                clearInterval(intervalId);
                window.location.reload();
            }
        });
    }, 6000);
})();
</script>

