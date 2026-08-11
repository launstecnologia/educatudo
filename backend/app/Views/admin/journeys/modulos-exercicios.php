<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gerenciar Exercícios - <?= htmlspecialchars($modulo['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Jornada: <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/jornadas/<?= $modulo['jornada_id'] ?>/modulos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Tabs: Criar Manual / Gerar por IA -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-purple-200">
    <div class="flex space-x-4 mb-6">
        <button onclick="mostrarFormManual()" id="tab-manual" class="btn-primary-custom tab-exercicio active px-6 py-3 rounded-lg font-medium">
            ✏️ Criar Manualmente
        </button>
        <button onclick="mostrarFormIA()" id="tab-ia" class="tab-exercicio px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">
            🤖 Gerar com IA
        </button>
    </div>
    
    <!-- Form Manual -->
    <div id="form-manual" class="exercicio-form">
        <form id="adicionarExercicioForm" class="space-y-4">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Exercício *</label>
                    <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="alternativas">Alternativas (Múltipla Escolha)</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pontuação</label>
                    <input type="number" name="pontuacao" step="0.1" min="0" value="1.00" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                <input type="text" name="titulo" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                       placeholder="Ex: Exercício sobre Equações">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado *</label>
                <textarea name="enunciado" rows="4" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder="Digite o enunciado do exercício..."></textarea>
            </div>
            
            <div id="opcoes-container" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas (Múltipla Escolha) *</label>
                <div id="opcoes-lista" class="space-y-3 mb-3"></div>
                <div class="flex items-center justify-between">
                    <button type="button" onclick="adicionarOpcao()" class="btn-primary-custom px-4 py-2 rounded text-sm transition-colors hover:opacity-90">
                        + Adicionar Alternativa
                    </button>
                    <span class="text-xs text-gray-500">Máximo 5 alternativas (A, B, C, D, E)</span>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Resposta Correta / Gabarito</label>
                <textarea name="resposta_correta" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder="Digite a resposta correta ou gabarito..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                Adicionar Exercício
            </button>
        </form>
    </div>
    
    <!-- Form IA -->
    <div id="form-ia" class="exercicio-form hidden">
        <form id="gerarExercicioIAForm" class="space-y-4">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Exercício *</label>
                    <select name="tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="alternativas">Alternativas (Múltipla Escolha)</option>
                        <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                        <option value="dissertativa">Dissertativa</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantidade *</label>
                    <input type="number" name="quantidade" min="1" max="20" value="5" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contexto Adicional (Opcional)</label>
                <textarea name="contexto" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder="Informações adicionais para ajudar a IA a gerar exercícios mais precisos..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                🤖 Gerar Exercícios com IA
            </button>
        </form>
    </div>
</div>

<!-- Lista de Exercícios -->
<div class="bg-white rounded-xl shadow-lg p-6 border border-purple-200">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Exercícios do Módulo</h3>
        <?php 
        $totalPublicados = 0;
        $totalRascunhos = 0;
        foreach ($exercicios as $ex) {
            if ($ex['status'] === 'publicado') $totalPublicados++;
            else $totalRascunhos++;
        }
        ?>
        <div class="flex space-x-3 text-sm">
            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full">
                ✅ <?= $totalPublicados ?> Publicado<?= $totalPublicados != 1 ? 's' : '' ?>
            </span>
            <?php if ($totalRascunhos > 0): ?>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                    📝 <?= $totalRascunhos ?> Rascunho<?= $totalRascunhos != 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="exerciciosList" class="space-y-4">
        <?php if (empty($exercicios)): ?>
            <div class="text-center py-8 text-gray-500">
                <p class="mb-2">Nenhum exercício criado ainda</p>
                <p class="text-sm text-gray-400">Crie exercícios manualmente ou gere com IA usando os formulários acima</p>
            </div>
        <?php else: ?>
            <?php foreach ($exercicios as $exercicio): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded mr-2">
                                    <?= ucfirst(str_replace('_', ' ', $exercicio['tipo'])) ?>
                                </span>
                                <?php if ($exercicio['gerado_ia']): ?>
                                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">🤖 IA</span>
                                <?php endif; ?>
                                <span class="px-2 py-1 text-xs <?= $exercicio['status'] === 'publicado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> rounded ml-2">
                                    <?= ucfirst($exercicio['status']) ?>
                                </span>
                            </div>
                            <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($exercicio['titulo']) ?></h4>
                            <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars(substr($exercicio['enunciado'], 0, 150)) ?><?= strlen($exercicio['enunciado']) > 150 ? '...' : '' ?></p>
                            <p class="text-xs text-gray-500 mt-2">Pontuação: <?= $exercicio['pontuacao'] ?> pontos</p>
                        </div>
                        <div class="flex space-x-2 ml-4">
                            <?php if ($exercicio['status'] === 'publicado'): ?>
                                <button onclick="alternarStatusExercicio(<?= $exercicio['id'] ?>)" 
                                        class="px-3 py-1 bg-yellow-500 text-white rounded text-sm hover:bg-yellow-600 transition-colors"
                                        title="Despublicar exercício">
                                    👁️ Despublicar
                                </button>
                            <?php else: ?>
                                <button onclick="alternarStatusExercicio(<?= $exercicio['id'] ?>)" 
                                        class="btn-primary-custom px-3 py-1 rounded text-sm transition-colors hover:opacity-90"
                                        title="Publicar exercício">
                                    ✅ Publicar
                                </button>
                            <?php endif; ?>
                            <button onclick="editarExercicio(<?= $exercicio['id'] ?>)" 
                                    class="btn-primary-custom px-3 py-1 rounded text-sm transition-colors hover:opacity-90">
                                Editar
                            </button>
                            <button onclick="removerExercicio(<?= $exercicio['id'] ?>)" 
                                    class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                                Remover
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/components/ai-job-poller.php'; ?>
<script>
const csrfTokenModuloExerciciosAdmin = <?= json_encode($csrf_token ?? '') ?>;
let opcoesCount = 0;
const letras = ['A', 'B', 'C', 'D', 'E'];

function mostrarFormManual() {
    document.getElementById('form-manual').classList.remove('hidden');
    document.getElementById('form-ia').classList.add('hidden');
    document.getElementById('tab-manual').classList.add('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-manual').classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById('tab-ia').classList.remove('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-ia').classList.add('bg-gray-200', 'text-gray-700');
}

function mostrarFormIA() {
    document.getElementById('form-manual').classList.add('hidden');
    document.getElementById('form-ia').classList.remove('hidden');
    document.getElementById('tab-ia').classList.add('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-ia').classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById('tab-manual').classList.remove('active', 'bg-purple-600', 'text-white');
    document.getElementById('tab-manual').classList.add('bg-gray-200', 'text-gray-700');
}

function adicionarOpcao() {
    if (opcoesCount >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    
    const letra = letras[opcoesCount];
    opcoesCount++;
    const container = document.getElementById('opcoes-lista');
    const div = document.createElement('div');
    div.className = 'flex items-center space-x-3 p-3 border border-gray-300 rounded-lg bg-gray-50';
    div.setAttribute('data-opcao-index', opcoesCount - 1);
    div.innerHTML = `
        <div class="flex items-center justify-center w-8 h-8 bg-purple-600 text-white rounded-full font-bold text-sm">
            ${letra}
        </div>
        <input type="text" name="opcoes[]" placeholder="Digite o texto da alternativa ${letra}" 
               class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
               required>
        <div class="flex items-center space-x-2">
            <input type="radio" name="resposta_opcao" value="${opcoesCount - 1}" id="radio-${opcoesCount - 1}" 
                   class="w-5 h-5 text-purple-600 focus:ring-purple-500">
            <label for="radio-${opcoesCount - 1}" class="text-sm text-gray-700 cursor-pointer">Correta</label>
        </div>
        <button type="button" onclick="removerOpcao(this)" 
                class="px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
            Remover
        </button>
    `;
    container.appendChild(div);
    atualizarIndicesOpcoes();
}

function removerOpcao(button) {
    const div = button.closest('div[data-opcao-index]');
    const index = parseInt(div.getAttribute('data-opcao-index'));
    div.remove();
    opcoesCount--;
    atualizarIndicesOpcoes();
}

function atualizarIndicesOpcoes() {
    const container = document.getElementById('opcoes-lista');
    const opcoes = container.querySelectorAll('div[data-opcao-index]');
    opcoes.forEach((opcao, index) => {
        const letra = letras[index];
        opcao.setAttribute('data-opcao-index', index);
        opcao.querySelector('.bg-purple-600').textContent = letra;
        opcao.querySelector('input[type="text"]').placeholder = `Digite o texto da alternativa ${letra}`;
        const radio = opcao.querySelector('input[type="radio"]');
        radio.value = index;
        radio.id = `radio-${index}`;
        opcao.querySelector('label').setAttribute('for', `radio-${index}`);
    });
}

document.getElementById('adicionarExercicioForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Coleta opções se for múltipla escolha
    if (formData.get('tipo') === 'alternativas') {
        const opcoes = [];
        const opcoesInputs = document.querySelectorAll('input[name="opcoes[]"]');
        const respostaIndex = formData.get('resposta_opcao');
        
        if (opcoesInputs.length < 2) {
            alert('Adicione pelo menos 2 alternativas');
            e.preventDefault();
            return;
        }
        
        if (!respostaIndex && respostaIndex !== '0') {
            alert('Selecione a alternativa correta');
            e.preventDefault();
            return;
        }
        
        opcoesInputs.forEach((input, index) => {
            if (input.value.trim()) {
                opcoes.push({
                    letra: letras[index],
                    texto: input.value.trim(),
                    correta: index.toString() === respostaIndex
                });
            }
        });
        
        if (opcoes.length < 2) {
            alert('Adicione pelo menos 2 alternativas válidas');
            e.preventDefault();
            return;
        }
        
        formData.set('questoes_json', JSON.stringify({ opcoes: opcoes }));
        formData.set('resposta_correta', letras[parseInt(respostaIndex)]);
    }
    
    fetch('<?= URL ?>/admin/jornadas/modulos/adicionar-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Exercício adicionado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
});

document.getElementById('gerarExercicioIAForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    if (!confirm('Deseja gerar exercícios com IA? O processamento ocorre em segundo plano.')) {
        return;
    }

    const loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'loading-overlay-ia-admin';
    loadingOverlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center; flex-direction: column;';
    loadingOverlay.innerHTML = `
        <div style="background: white; padding: 24px; border-radius: 12px; text-align: center; max-width: 360px; width: calc(100% - 32px);">
            <p id="loading-ia-titulo-admin" style="font-size: 18px; color: #1f2937; margin: 0; font-weight: 600;">Enfileirando na Tudinha...</p>
            <p id="loading-ia-subtitulo-admin" style="font-size: 14px; color: #6b7280; margin-top: 8px;">Aguarde sem fechar a página.</p>
        </div>
    `;
    document.body.appendChild(loadingOverlay);

    function removerLoading() {
        if (loadingOverlay.parentNode) loadingOverlay.parentNode.removeChild(loadingOverlay);
    }
    function falhar(msg) {
        removerLoading();
        alert('Erro ao gerar exercícios com IA.' + (msg ? ('\nDetalhe: ' + msg) : ''));
    }

    fetch('<?= URL ?>/admin/jornadas/modulos/gerar-exercicio-ia', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
    .then(function(response) {
        return response.json().then(function(body) {
            return { ok: response.ok, body: body };
        });
    })
    .then(function(res) {
        if (!res.ok || !res.body.success || !res.body.job_id) {
            throw new Error((res.body && res.body.error) ? res.body.error : 'Não foi possível iniciar a geração');
        }

        var tituloEl = document.getElementById('loading-ia-titulo-admin');
        var subEl = document.getElementById('loading-ia-subtitulo-admin');
        if (tituloEl) tituloEl.textContent = 'A Tudinha está criando os exercícios...';

        new AIJobPoller(res.body.job_id, {
            onProgress: function(status) {
                if (tituloEl) {
                    tituloEl.textContent = status === 'pending'
                        ? 'Na fila da Tudinha...'
                        : 'A Tudinha está criando os exercícios...';
                }
            },
            onDone: function() {
                if (tituloEl) tituloEl.textContent = 'Salvando exercícios...';
                if (subEl) subEl.textContent = 'Quase lá.';
                var fd = new FormData();
                fd.append('_token', csrfTokenModuloExerciciosAdmin);
                fetch('<?= URL ?>/admin/jornadas/modulos/importar-exercicios-ia/' + res.body.job_id, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json().then(function(body) { return { ok: r.ok, body: body }; }); })
                .then(function(imp) {
                    removerLoading();
                    if (!imp.ok || !imp.body.success) {
                        falhar((imp.body && imp.body.error) ? imp.body.error : 'Exercícios gerados, mas falha ao salvar.');
                        return;
                    }
                    alert((imp.body.exercicios_ids ? imp.body.exercicios_ids.length : 0) + ' exercício(s) gerado(s) com sucesso!');
                    location.reload();
                })
                .catch(function(err) {
                    falhar(err && err.message ? err.message : 'Falha ao salvar os exercícios gerados.');
                });
            },
            onFailed: function(err) {
                falhar(err || 'Falha no processamento da IA.');
            }
        });
    })
    .catch(function(error) {
        falhar(error && error.message ? error.message : '');
        console.error(error);
    });
});

// Função para mostrar/esconder opções baseado no tipo
function atualizarOpcoesAlternativas(selectElement) {
    const container = document.getElementById('opcoes-container');
    if (selectElement.value === 'alternativas') {
        container.classList.remove('hidden');
        // Adiciona pelo menos 2 opções automaticamente
        if (opcoesCount === 0) {
            adicionarOpcao();
            adicionarOpcao();
        }
    } else {
        container.classList.add('hidden');
        // Limpa opções quando muda o tipo
        document.getElementById('opcoes-lista').innerHTML = '';
        opcoesCount = 0;
    }
}

// Função para inicializar o select de tipo
function inicializarSelectTipo() {
    const tipoSelect = document.querySelector('select[name="tipo"]');
    if (!tipoSelect) {
        // Se não encontrou, tenta novamente após um delay
        setTimeout(inicializarSelectTipo, 100);
        return;
    }
    
    // Adiciona listener de mudança
    tipoSelect.addEventListener('change', function() {
        atualizarOpcoesAlternativas(this);
    });
    
    // Verifica se já está selecionado como alternativas
    if (tipoSelect.value === 'alternativas') {
        atualizarOpcoesAlternativas(tipoSelect);
    }
}

// Inicializa quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarSelectTipo);
} else {
    // DOM já está pronto
    inicializarSelectTipo();
}

function alternarStatusExercicio(id) {
    const formData = new FormData();
    formData.append('exercicio_id', id);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/alternar-status-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const statusText = data.novo_status === 'publicado' ? 'publicado' : 'despublicado';
            alert('Exercício ' + statusText + ' com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}

function removerExercicio(id) {
    if (!confirm('Tem certeza que deseja remover este exercício?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('exercicio_id', id);
    
    fetch('<?= URL ?>/admin/jornadas/modulos/remover-exercicio', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Exercício removido com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro de conexão');
        console.error(error);
    });
}

let exercicioEditando = null;

function editarExercicio(id) {
    // Busca dados do exercício
    fetch(`<?= URL ?>/admin/jornadas/modulos/buscar-exercicio?exercicio_id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Exercício carregado:', data.exercicio);
                exercicioEditando = data.exercicio;
                abrirModalEdicao(data.exercicio);
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            alert('Erro de conexão');
            console.error(error);
        });
}

function abrirModalEdicao(exercicio) {
    // Remove modal anterior se existir
    const modalAnterior = document.getElementById('modal-editar-exercicio');
    if (modalAnterior) {
        modalAnterior.remove();
    }
    
    // Cria novo modal
    const modal = document.createElement('div');
    modal.id = 'modal-editar-exercicio';
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Editar Exercício</h3>
                    <button onclick="fecharModalEdicao()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="form-editar-exercicio" class="space-y-4">
                    <input type="hidden" name="exercicio_id" id="edit-exercicio-id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Exercício *</label>
                            <select name="tipo" id="edit-tipo" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <option value="alternativas">Alternativas (Múltipla Escolha)</option>
                                <option value="verdadeiro_falso">Verdadeiro ou Falso</option>
                                <option value="dissertativa">Dissertativa</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pontuação</label>
                            <input type="number" name="pontuacao" id="edit-pontuacao" step="0.1" min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                        <input type="text" name="titulo" id="edit-titulo" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Enunciado *</label>
                        <div id="edit-enunciado-preview" class="mb-2 p-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 text-sm min-h-[60px]" style="display:none;"></div>
                        <textarea name="enunciado" id="edit-enunciado" rows="4" required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>
                    
                    <div id="edit-opcoes-container" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alternativas (Múltipla Escolha) *</label>
                        <div id="edit-opcoes-lista" class="space-y-3 mb-3"></div>
                        <div class="flex items-center justify-between">
                            <button type="button" onclick="adicionarOpcaoEdicao()" class="btn-primary-custom px-4 py-2 rounded text-sm transition-colors hover:opacity-90">
                                + Adicionar Alternativa
                            </button>
                            <span class="text-xs text-gray-500">Máximo 5 alternativas (A, B, C, D, E)</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resposta Correta / Gabarito</label>
                        <textarea name="resposta_correta" id="edit-resposta_correta" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="edit-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="rascunho">Rascunho</option>
                            <option value="publicado">Publicado</option>
                            <option value="arquivado">Arquivado</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t">
                        <button type="button" onclick="fecharModalEdicao()" 
                                class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        `;
    document.body.appendChild(modal);
    
    // Define a variável global antes de preencher o formulário
    exercicioEditando = exercicio;
    
    // Preenche formulário
    document.getElementById('edit-exercicio-id').value = exercicio.id;
    document.getElementById('edit-tipo').value = exercicio.tipo || 'alternativas';
    document.getElementById('edit-titulo').value = exercicio.titulo || '';
    var enunciadoVal = exercicio.enunciado || '';
    document.getElementById('edit-enunciado').value = enunciadoVal;
    var previewEnunciado = document.getElementById('edit-enunciado-preview');
    if (previewEnunciado) {
        previewEnunciado.innerHTML = enunciadoVal.replace(/\n/g, '<br>');
        previewEnunciado.style.display = enunciadoVal ? 'block' : 'none';
    }
    document.getElementById('edit-pontuacao').value = exercicio.pontuacao || '1.00';
    document.getElementById('edit-resposta_correta').value = exercicio.resposta_correta || '';
    // Preserva o status atual ou usa 'publicado' como padrão se não tiver
    document.getElementById('edit-status').value = exercicio.status || 'publicado';
    
    // Carrega opções se for alternativas
    console.log('Tipo:', exercicio.tipo);
    console.log('questoes_json:', exercicio.questoes_json);
    console.log('Status:', exercicio.status);
    
    if (exercicio.tipo === 'alternativas') {
        const container = document.getElementById('edit-opcoes-container');
        const lista = document.getElementById('edit-opcoes-lista');
        container.classList.remove('hidden');
        lista.innerHTML = '';
        opcoesCountEdicao = 0;
        
        // Verifica se tem opções no JSON - tenta diferentes formatos
        let opcoes = null;
        
        if (exercicio.questoes_json) {
            // Se for objeto com propriedade opcoes
            if (exercicio.questoes_json.opcoes && Array.isArray(exercicio.questoes_json.opcoes)) {
                opcoes = exercicio.questoes_json.opcoes;
            }
            // Se for array direto
            else if (Array.isArray(exercicio.questoes_json)) {
                opcoes = exercicio.questoes_json;
            }
            // Se for string JSON, tenta decodificar
            else if (typeof exercicio.questoes_json === 'string') {
                try {
                    const parsed = JSON.parse(exercicio.questoes_json);
                    if (parsed && parsed.opcoes && Array.isArray(parsed.opcoes)) {
                        opcoes = parsed.opcoes;
                    } else if (Array.isArray(parsed)) {
                        opcoes = parsed;
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                }
            }
        }
        
        if (opcoes && opcoes.length > 0) {
            console.log('Carregando opções encontradas:', opcoes);
            opcoes.forEach((opcao) => {
                adicionarOpcaoEdicao(opcao);
            });
            
            // Marca a alternativa correta no radio button após um pequeno delay
            setTimeout(() => {
                let respostaCorretaEncontrada = false;
                
                // Primeiro tenta usar a marcação correta no JSON
                opcoes.forEach((opcao, index) => {
                    if (opcao.correta === true || opcao.correta === 1 || opcao.correta === '1' || opcao.correta === true) {
                        const radio = document.getElementById(`radio-edit-${index}`);
                        if (radio) {
                            radio.checked = true;
                            respostaCorretaEncontrada = true;
                            console.log('Alternativa correta encontrada no JSON:', index);
                        }
                    }
                });
                
                // Se não encontrou no JSON, tenta usar o campo resposta_correta
                if (!respostaCorretaEncontrada && exercicio.resposta_correta) {
                    const letraCorreta = exercicio.resposta_correta.trim().toUpperCase();
                    const indexCorreto = letras.indexOf(letraCorreta);
                    console.log('Tentando usar resposta_correta:', letraCorreta, 'index:', indexCorreto);
                    if (indexCorreto >= 0 && indexCorreto < opcoesCountEdicao) {
                        const radio = document.getElementById(`radio-edit-${indexCorreto}`);
                        if (radio) {
                            radio.checked = true;
                            console.log('Alternativa correta marcada via resposta_correta:', indexCorreto);
                        }
                    }
                }
            }, 200);
        } else {
            console.log('Nenhuma opção encontrada, adicionando opções vazias');
            // Se não tem opções, adiciona duas vazias
            adicionarOpcaoEdicao();
            adicionarOpcaoEdicao();
        }
    } else {
        document.getElementById('edit-opcoes-container').classList.add('hidden');
    }
    
    // Mostra modal
    modal.classList.remove('hidden');
    
    // Renderizar LaTeX no modal após conteúdo dinâmico
    if (window.MathJax && window.MathJax.typesetPromise) {
        MathJax.typesetPromise([modal]).catch(function(err) { console.warn('MathJax typeset:', err); });
    } else {
        var checkMathJax = setInterval(function() {
            if (window.MathJax && window.MathJax.typesetPromise) {
                clearInterval(checkMathJax);
                MathJax.typesetPromise([modal]).catch(function(err) { console.warn('MathJax typeset:', err); });
            }
        }, 100);
        setTimeout(function() { clearInterval(checkMathJax); }, 5000);
    }
    
    // Adiciona listener do tipo
    const tipoSelect = document.getElementById('edit-tipo');
    tipoSelect.addEventListener('change', function() {
        const container = document.getElementById('edit-opcoes-container');
        if (this.value === 'alternativas') {
            container.classList.remove('hidden');
            if (opcoesCountEdicao === 0) {
                adicionarOpcaoEdicao();
                adicionarOpcaoEdicao();
            }
        } else {
            container.classList.add('hidden');
            document.getElementById('edit-opcoes-lista').innerHTML = '';
            opcoesCountEdicao = 0;
        }
    });
    
    // Adiciona listener do formulário e preview do enunciado (MathJax)
    setTimeout(() => {
        adicionarListenerFormEdicao();
        var editEnunciado = document.getElementById('edit-enunciado');
        var previewEnunciado = document.getElementById('edit-enunciado-preview');
        if (editEnunciado && previewEnunciado) {
            editEnunciado.addEventListener('input', function() {
                previewEnunciado.innerHTML = this.value.replace(/\n/g, '<br>');
                previewEnunciado.style.display = this.value ? 'block' : 'none';
                if (window.MathJax && window.MathJax.typesetPromise) {
                    MathJax.typesetPromise([previewEnunciado]).catch(function(err) { console.warn('MathJax typeset:', err); });
                }
            });
        }
    }, 100);
}

function fecharModalEdicao() {
    const modal = document.getElementById('modal-editar-exercicio');
    if (modal) {
        modal.classList.add('hidden');
    }
    exercicioEditando = null;
    opcoesCountEdicao = 0;
}

let opcoesCountEdicao = 0;

function adicionarOpcaoEdicao(opcaoExistente = null) {
    if (opcoesCountEdicao >= 5) {
        alert('Máximo de 5 alternativas permitidas (A, B, C, D, E)');
        return;
    }
    
    const letra = letras[opcoesCountEdicao];
    const index = opcoesCountEdicao;
    opcoesCountEdicao++;
    const container = document.getElementById('edit-opcoes-lista');
    const div = document.createElement('div');
    div.className = 'flex items-center space-x-3 p-3 border border-gray-300 rounded-lg bg-gray-50';
    div.setAttribute('data-opcao-index', index);
    
    // Extrai o texto da opção - tenta diferentes propriedades possíveis
    let texto = '';
    if (opcaoExistente) {
        texto = opcaoExistente.texto || opcaoExistente.text || opcaoExistente.label || opcaoExistente.conteudo || '';
    }
    
    // Verifica se é a opção correta - tenta diferentes formatos
    let correta = false;
    if (opcaoExistente) {
        correta = opcaoExistente.correta === true || 
                  opcaoExistente.correta === 1 || 
                  opcaoExistente.correta === '1' ||
                  opcaoExistente.correta === 'true' ||
                  (opcaoExistente.letra && typeof exercicioEditando !== 'undefined' && exercicioEditando && exercicioEditando.resposta_correta && 
                   opcaoExistente.letra.toUpperCase() === exercicioEditando.resposta_correta.trim().toUpperCase());
    }
    
    console.log(`Adicionando opção ${letra}: texto="${texto}", correta=${correta}`);
    
    const inputTexto = document.createElement('input');
    inputTexto.type = 'text';
    inputTexto.name = 'opcoes[]';
    inputTexto.value = texto;
    inputTexto.placeholder = `Digite o texto da alternativa ${letra}`;
    inputTexto.className = 'flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500';
    inputTexto.required = true;
    
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'resposta_opcao_edit';
    radio.value = index;
    radio.id = `radio-edit-${index}`;
    radio.className = 'w-5 h-5 text-purple-600 focus:ring-purple-500';
    if (correta) {
        radio.checked = true;
    }
    
    const labelRadio = document.createElement('label');
    labelRadio.htmlFor = `radio-edit-${index}`;
    labelRadio.className = 'text-sm text-gray-700 cursor-pointer';
    labelRadio.textContent = 'Correta';
    
    const btnRemover = document.createElement('button');
    btnRemover.type = 'button';
    btnRemover.onclick = function() { removerOpcaoEdicao(this); };
    btnRemover.className = 'px-3 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors';
    btnRemover.textContent = 'Remover';
    
    const divLetra = document.createElement('div');
    divLetra.className = 'flex items-center justify-center w-8 h-8 bg-purple-600 text-white rounded-full font-bold text-sm';
    divLetra.textContent = letra;
    
    const divRadio = document.createElement('div');
    divRadio.className = 'flex items-center space-x-2';
    divRadio.appendChild(radio);
    divRadio.appendChild(labelRadio);
    
    div.appendChild(divLetra);
    div.appendChild(inputTexto);
    div.appendChild(divRadio);
    div.appendChild(btnRemover);
    
    container.appendChild(div);
    atualizarIndicesOpcoesEdicao();
}

function removerOpcaoEdicao(button) {
    const div = button.closest('div[data-opcao-index]');
    div.remove();
    opcoesCountEdicao--;
    atualizarIndicesOpcoesEdicao();
}

function atualizarIndicesOpcoesEdicao() {
    const container = document.getElementById('edit-opcoes-lista');
    const opcoes = container.querySelectorAll('div[data-opcao-index]');
    opcoes.forEach((opcao, index) => {
        const letra = letras[index];
        opcao.setAttribute('data-opcao-index', index);
        opcao.querySelector('.bg-purple-600').textContent = letra;
        opcao.querySelector('input[type="text"]').placeholder = `Digite o texto da alternativa ${letra}`;
        const radio = opcao.querySelector('input[type="radio"]');
        radio.value = index;
        radio.id = `radio-edit-${index}`;
        opcao.querySelector('label').setAttribute('for', `radio-edit-${index}`);
    });
}

// Listener do formulário de edição (adicionado dinamicamente quando o modal é criado)
function adicionarListenerFormEdicao() {
    const formEdit = document.getElementById('form-editar-exercicio');
    if (formEdit && !formEdit.hasAttribute('data-listener-adicionado')) {
        formEdit.setAttribute('data-listener-adicionado', 'true');
        formEdit.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                // Coleta opções se for múltipla escolha
                if (formData.get('tipo') === 'alternativas') {
                    const opcoes = [];
                    const opcoesInputs = document.querySelectorAll('#edit-opcoes-lista input[name="opcoes[]"]');
                    const respostaIndex = formData.get('resposta_opcao_edit');
                    
                    if (opcoesInputs.length < 2) {
                        alert('Adicione pelo menos 2 alternativas');
                        return;
                    }
                    
                    if (respostaIndex === null) {
                        alert('Selecione a alternativa correta');
                        return;
                    }
                    
                    opcoesInputs.forEach((input, index) => {
                        if (input.value.trim()) {
                            opcoes.push({
                                letra: letras[index],
                                texto: input.value.trim(),
                                correta: index.toString() === respostaIndex
                            });
                        }
                    });
                    
                    if (opcoes.length < 2) {
                        alert('Adicione pelo menos 2 alternativas válidas');
                        return;
                    }
                    
                    formData.set('questoes_json', JSON.stringify({ opcoes: opcoes }));
                    formData.set('resposta_correta', letras[parseInt(respostaIndex)]);
                }
                
                fetch('<?= URL ?>/admin/jornadas/modulos/atualizar-exercicio', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Exercício atualizado com sucesso!');
                        fecharModalEdicao();
                        location.reload();
                    } else {
                        alert('Erro: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    alert('Erro de conexão');
                    console.error(error);
                });
            });
    }
}

</script>

