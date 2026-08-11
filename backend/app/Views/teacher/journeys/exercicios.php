<?php
/**
 * EducaTudo - View para Gerenciar Exercícios da Jornada
 * Interface para professores visualizarem e gerenciarem exercícios
 */
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Exercícios da Jornada</h1>
                    <p class="mt-2 text-gray-600">
                        Gerencie os exercícios de "<?= htmlspecialchars($jornada['titulo']) ?>"
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/exercicios/criar" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Novo Exercício
                    </a>
                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar
                    </a>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total de Exercícios</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count($exercicios) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Publicados</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= count(array_filter($exercicios, fn($e) => $e['status'] === 'publicado')) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pendentes</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= count(array_filter($exercicios, fn($e) => $e['status'] === 'pendente')) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Gerados por IA</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= count(array_filter($exercicios, fn($e) => $e['tipo'] === 'ia')) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Filtros</h3>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="filtroStatus" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="publicado">Publicado</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select id="filtroTipo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="manual">Manual</option>
                            <option value="ia">IA</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dificuldade</label>
                        <select id="filtroDificuldade" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <option value="fácil">Fácil</option>
                            <option value="médio">Médio</option>
                            <option value="difícil">Difícil</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Aula</label>
                        <select id="filtroAula" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <?php foreach ($aulas as $aula): ?>
                                <option value="<?= $aula['id'] ?>"><?= htmlspecialchars($aula['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Exercícios -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Exercícios</h3>
            </div>
            
            <?php if (empty($exercicios)): ?>
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum exercício encontrado</h3>
                    <p class="mt-1 text-sm text-gray-500">Comece criando seu primeiro exercício.</p>
                    <div class="mt-6">
                        <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/exercicios/criar" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Criar Exercício
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($exercicios as $exercicio): ?>
                        <div class="exercicio-item px-6 py-4 hover:bg-gray-50" 
                             data-status="<?= $exercicio['status'] ?>"
                             data-tipo="<?= $exercicio['tipo'] ?>"
                             data-dificuldade="<?= $exercicio['dificuldade'] ?>"
                             data-aula="<?= $exercicio['aula_id'] ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <!-- Tipo -->
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                   <?= $exercicio['tipo'] === 'ia' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                            <?= $exercicio['tipo'] === 'ia' ? 'IA' : 'Manual' ?>
                                        </span>
                                        
                                        <!-- Status -->
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                   <?php
                                                   switch($exercicio['status']) {
                                                       case 'publicado': echo 'bg-green-100 text-green-800'; break;
                                                       case 'aprovado': echo 'bg-blue-100 text-blue-800'; break;
                                                       case 'pendente': echo 'bg-yellow-100 text-yellow-800'; break;
                                                       default: echo 'bg-gray-100 text-gray-800';
                                                   }
                                                   ?>">
                                            <?= ucfirst($exercicio['status']) ?>
                                        </span>
                                        
                                        <!-- Dificuldade -->
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <?= ucfirst($exercicio['dificuldade']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <h4 class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars(substr($exercicio['enunciado'], 0, 100)) ?>
                                            <?= strlen($exercicio['enunciado']) > 100 ? '...' : '' ?>
                                        </h4>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Aula: <?= htmlspecialchars($exercicio['nome_aula'] ?? 'Geral') ?> • 
                                            Criado em <?= date('d/m/Y H:i', strtotime($exercicio['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <!-- Ações baseadas no status -->
                                    <?php if ($exercicio['status'] === 'pendente'): ?>
                                        <button onclick="aprovarExercicio(<?= $exercicio['id'] ?>)" 
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Aprovar
                                        </button>
                                    <?php elseif ($exercicio['status'] === 'aprovado'): ?>
                                        <button onclick="publicarExercicio(<?= $exercicio['id'] ?>)" 
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                            </svg>
                                            Publicar
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Editar -->
                                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/exercicios/<?= $exercicio['id'] ?>/editar" 
                                       class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Editar
                                    </a>
                                    
                                    <!-- Visualizar -->
                                    <button onclick="visualizarExercicio(<?= $exercicio['id'] ?>)" 
                                            class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Ver
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Visualizar Exercício -->
<div id="modalVisualizar" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Visualizar Exercício</h3>
            </div>
            <div class="px-6 py-4">
                <div id="conteudoExercicio">
                    <!-- Conteúdo será inserido aqui via JavaScript -->
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button type="button" id="fecharModal" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroStatus = document.getElementById('filtroStatus');
    const filtroTipo = document.getElementById('filtroTipo');
    const filtroDificuldade = document.getElementById('filtroDificuldade');
    const filtroAula = document.getElementById('filtroAula');
    const exercicioItems = document.querySelectorAll('.exercicio-item');
    
    // Aplicar filtros
    function aplicarFiltros() {
        const status = filtroStatus.value;
        const tipo = filtroTipo.value;
        const dificuldade = filtroDificuldade.value;
        const aula = filtroAula.value;
        
        exercicioItems.forEach(item => {
            let mostrar = true;
            
            if (status && item.dataset.status !== status) mostrar = false;
            if (tipo && item.dataset.tipo !== tipo) mostrar = false;
            if (dificuldade && item.dataset.dificuldade !== dificuldade) mostrar = false;
            if (aula && item.dataset.aula !== aula) mostrar = false;
            
            item.style.display = mostrar ? 'block' : 'none';
        });
    }
    
    // Event listeners para filtros
    filtroStatus.addEventListener('change', aplicarFiltros);
    filtroTipo.addEventListener('change', aplicarFiltros);
    filtroDificuldade.addEventListener('change', aplicarFiltros);
    filtroAula.addEventListener('change', aplicarFiltros);
    
    // Fechar modal
    document.getElementById('fecharModal').addEventListener('click', function() {
        document.getElementById('modalVisualizar').classList.add('hidden');
    });
});

// Aprovar exercício
function aprovarExercicio(exercicioId) {
    if (!confirm('Tem certeza que deseja aprovar este exercício?')) return;
    
    fetch('<?= URL ?>/professor/jornadas/aprovar-exercicio-ia', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            '_token': <?= json_encode($csrf_token) ?>,
            'exercicio_id': exercicioId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Exercício aprovado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao aprovar exercício: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao aprovar exercício. Tente novamente.');
    });
}

// Publicar exercício
function publicarExercicio(exercicioId) {
    if (!confirm('Tem certeza que deseja publicar este exercício para os alunos?')) return;
    
    fetch('<?= URL ?>/professor/jornadas/publicar-exercicio', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            '_token': <?= json_encode($csrf_token) ?>,
            'exercicio_id': exercicioId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Exercício publicado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao publicar exercício: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao publicar exercício. Tente novamente.');
    });
}

// Visualizar exercício
function visualizarExercicio(exercicioId) {
    // Buscar dados do exercício
    fetch(`<?= URL ?>/professor/jornadas/exercicios/${exercicioId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarExercicio(data.exercicio);
            document.getElementById('modalVisualizar').classList.remove('hidden');
        } else {
            alert('Erro ao carregar exercício: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar exercício. Tente novamente.');
    });
}

// Mostrar exercício no modal
function mostrarExercicio(exercicio) {
    const conteudo = document.getElementById('conteudoExercicio');
    const alternativas = JSON.parse(exercicio.alternativas || '[]');
    
    conteudo.innerHTML = `
        <div class="space-y-4">
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Enunciado:</h4>
                <p class="text-gray-700 bg-gray-50 p-3 rounded">${exercicio.enunciado}</p>
            </div>
            
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Alternativas:</h4>
                <div class="space-y-2">
                    ${alternativas.map((alt, index) => `
                        <div class="flex items-center space-x-2">
                            <span class="w-6 h-6 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-sm font-medium">
                                ${String.fromCharCode(65 + index)}
                            </span>
                            <span class="text-gray-700">${alt}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Resposta Correta:</h4>
                <p class="text-green-600 font-medium">${exercicio.resposta_correta}</p>
            </div>
            
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Explicação:</h4>
                <p class="text-gray-700 bg-gray-50 p-3 rounded">${exercicio.explicacao || 'Sem explicação'}</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-medium text-gray-900">Dificuldade:</span>
                    <span class="text-gray-700">${exercicio.dificuldade}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-900">Tipo:</span>
                    <span class="text-gray-700">${exercicio.tipo === 'ia' ? 'Gerado por IA' : 'Manual'}</span>
                </div>
            </div>
        </div>
    `;
}
</script>
