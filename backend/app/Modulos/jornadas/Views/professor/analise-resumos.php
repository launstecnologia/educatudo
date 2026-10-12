<?php
/**
 * EducaTudo - View para Análise de Resumos dos Alunos
 * Interface para professores visualizarem e analisarem resumos dos alunos
 */
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Análise de Resumos</h1>
                    <p class="mt-2 text-gray-600">
                        Analise os resumos dos alunos da jornada "<?= htmlspecialchars($jornada['titulo']) ?>"
                    </p>
                </div>
                <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Voltar
                </a>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total de Alunos</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count($alunos) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Resumos Entregues</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= count(array_filter($resumos, fn($r) => !empty($r['resumo_texto']) || !empty($r['resumo_aluno']))) ?>
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
                        <p class="text-sm font-medium text-gray-500">Analisados por IA</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= count(array_filter($resumos, fn($r) => !empty($r['analise_ia']))) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Média de Pontuação</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $pontuacoes = array_filter(array_map(function($r) {
                                $analise = json_decode($r['analise_ia'] ?? '{}', true);
                                return isset($analise['pontuacao']) ? (float)$analise['pontuacao'] : null;
                            }, $resumos));
                            echo !empty($pontuacoes) ? number_format(array_sum($pontuacoes) / count($pontuacoes), 1) : '0.0';
                            ?>
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Aula</label>
                        <select id="filtroAula" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas as aulas</option>
                            <?php foreach ($aulas as $aula): ?>
                                <option value="<?= $aula['id'] ?>"><?= htmlspecialchars($aula['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="filtroStatus" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="entregue">Entregues</option>
                            <option value="pendente">Pendentes</option>
                            <option value="analisado">Analisados</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nível de Compreensão</label>
                        <select id="filtroNivel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="básico">Básico</option>
                            <option value="intermediário">Intermediário</option>
                            <option value="avançado">Avançado</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Resumos -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Resumos dos Alunos</h3>
            </div>
            
            <?php if (empty($resumos)): ?>
                <div class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Nenhum resumo encontrado</h3>
                    <p class="mt-1 text-sm text-gray-500">Os alunos ainda não entregaram resumos para esta jornada.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($resumos as $resumo): ?>
                        <?php 
                        $analise = json_decode($resumo['analise_ia'] ?? '{}', true);
                        $pontuacao = isset($analise['pontuacao']) ? (float)$analise['pontuacao'] : 0;
                        $nivelCompreensao = $analise['nivel_compreensao'] ?? 'não analisado';
                        ?>
                        <div class="resumo-item px-6 py-4 hover:bg-gray-50" 
                             data-aula="<?= $resumo['aula_id'] ?>"
                             data-status="<?= (!empty($resumo['resumo_texto']) || !empty($resumo['resumo_aluno'])) ? 'entregue' : 'pendente' ?>"
                             data-nivel="<?= $nivelCompreensao ?>">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <!-- Avatar do aluno -->
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-medium text-blue-800">
                                                    <?= strtoupper(substr($resumo['nome_aluno'], 0, 2)) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Nome do aluno -->
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($resumo['nome_aluno']) ?>
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                Aula: <?= htmlspecialchars($resumo['nome_aula']) ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Status -->
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                   <?= !empty($resumo['resumo_texto']) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= !empty($resumo['resumo_texto']) ? 'Entregue' : 'Pendente' ?>
                                        </span>
                                        
                                        <!-- Nível de Compreensão -->
                                        <?php if (!empty($analise)): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                       <?php
                                                       switch($nivelCompreensao) {
                                                           case 'avançado': echo 'bg-green-100 text-green-800'; break;
                                                           case 'intermediário': echo 'bg-blue-100 text-blue-800'; break;
                                                           case 'básico': echo 'bg-yellow-100 text-yellow-800'; break;
                                                           default: echo 'bg-gray-100 text-gray-800';
                                                       }
                                                       ?>">
                                                <?= ucfirst($nivelCompreensao) ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Pontuação -->
                                        <?php if ($pontuacao > 0): ?>
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-900"><?= number_format($pontuacao, 1) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Resumo do aluno (resumo_texto = por aula, resumo_aluno = por módulo) -->
                                    <?php $textoResumo = !empty($resumo['resumo_texto']) ? $resumo['resumo_texto'] : ($resumo['resumo_aluno'] ?? ''); ?>
                                    <?php if ($textoResumo !== ''): ?>
                                        <div class="mt-3">
                                            <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded">
                                                <?= htmlspecialchars(substr($textoResumo, 0, 200)) ?>
                                                <?= strlen($textoResumo) > 200 ? '...' : '' ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Análise da IA -->
                                        <?php if (!empty($analise)): ?>
                                            <div class="mt-3 space-y-2">
                                                <!-- Pontos Acertados -->
                                                <?php if (!empty($analise['pontos_acertados'])): ?>
                                                    <div>
                                                        <h5 class="text-xs font-medium text-green-700 mb-1">Pontos Acertados:</h5>
                                                        <ul class="text-xs text-green-600 space-y-1">
                                                            <?php foreach (array_slice($analise['pontos_acertados'], 0, 3) as $ponto): ?>
                                                                <li>• <?= htmlspecialchars($ponto) ?></li>
                                                            <?php endforeach; ?>
                                                            <?php if (count($analise['pontos_acertados']) > 3): ?>
                                                                <li>• ... e mais <?= count($analise['pontos_acertados']) - 3 ?> pontos</li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Lacunas Identificadas -->
                                                <?php if (!empty($analise['lacunas_identificadas'])): ?>
                                                    <div>
                                                        <h5 class="text-xs font-medium text-red-700 mb-1">Lacunas Identificadas:</h5>
                                                        <ul class="text-xs text-red-600 space-y-1">
                                                            <?php foreach (array_slice($analise['lacunas_identificadas'], 0, 2) as $lacuna): ?>
                                                                <li>• <?= htmlspecialchars($lacuna) ?></li>
                                                            <?php endforeach; ?>
                                                            <?php if (count($analise['lacunas_identificadas']) > 2): ?>
                                                                <li>• ... e mais <?= count($analise['lacunas_identificadas']) - 2 ?> lacunas</li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="mt-3">
                                            <p class="text-sm text-gray-500 italic">Aluno ainda não entregou o resumo</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-xs text-gray-500 mt-2">
                                        Entregue em: <?= $resumo['created_at'] ? date('d/m/Y H:i', strtotime($resumo['created_at'])) : 'Não entregue' ?>
                                    </p>
                                </div>
                                
                                <div class="flex items-center space-x-2 ml-4">
                                    <!-- Ver detalhes -->
                                    <button onclick="verDetalhesResumo(<?= $resumo['id'] ?>)" 
                                            class="inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Ver Detalhes
                                    </button>
                                    
                                    <!-- Gerar explicação complementar -->
                                    <?php if (!empty($analise['lacunas_identificadas'])): ?>
                                        <button onclick="gerarExplicacaoComplementar(<?= $resumo['id'] ?>)" 
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-purple-700 bg-purple-100 hover:bg-purple-200">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                            </svg>
                                            Explicação IA
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Detalhes do Resumo -->
<div id="modalDetalhes" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Detalhes do Resumo</h3>
            </div>
            <div class="px-6 py-4">
                <div id="conteudoDetalhes">
                    <!-- Conteúdo será inserido aqui via JavaScript -->
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button type="button" id="fecharModalDetalhes" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Explicação Complementar -->
<div id="modalExplicacao" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-medium text-gray-900">Explicação Complementar</h3>
            </div>
            <div class="px-6 py-4">
                <div id="conteudoExplicacao">
                    <!-- Conteúdo será inserido aqui via JavaScript -->
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button type="button" id="fecharModalExplicacao" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroAula = document.getElementById('filtroAula');
    const filtroStatus = document.getElementById('filtroStatus');
    const filtroNivel = document.getElementById('filtroNivel');
    const resumoItems = document.querySelectorAll('.resumo-item');
    
    // Aplicar filtros
    function aplicarFiltros() {
        const aula = filtroAula.value;
        const status = filtroStatus.value;
        const nivel = filtroNivel.value;
        
        resumoItems.forEach(item => {
            let mostrar = true;
            
            if (aula && item.dataset.aula !== aula) mostrar = false;
            if (status && item.dataset.status !== status) mostrar = false;
            if (nivel && item.dataset.nivel !== nivel) mostrar = false;
            
            item.style.display = mostrar ? 'block' : 'none';
        });
    }
    
    // Event listeners para filtros
    filtroAula.addEventListener('change', aplicarFiltros);
    filtroStatus.addEventListener('change', aplicarFiltros);
    filtroNivel.addEventListener('change', aplicarFiltros);
    
    // Fechar modais
    document.getElementById('fecharModalDetalhes').addEventListener('click', function() {
        document.getElementById('modalDetalhes').classList.add('hidden');
    });
    
    document.getElementById('fecharModalExplicacao').addEventListener('click', function() {
        document.getElementById('modalExplicacao').classList.add('hidden');
    });
});

// Ver detalhes do resumo
function verDetalhesResumo(resumoId) {
    fetch(`<?= URL ?>/professor/jornadas/resumos/${resumoId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarDetalhesResumo(data.resumo);
            document.getElementById('modalDetalhes').classList.remove('hidden');
        } else {
            alert('Erro ao carregar detalhes: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar detalhes. Tente novamente.');
    });
}

// Abrir modal automaticamente se houver resumo_id_especifico
<?php if (!empty($resumo_id_especifico) && !empty($resumo_especifico)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Abre o modal automaticamente
    verDetalhesResumo(<?= $resumo_id_especifico ?>);
});
<?php endif; ?>

// Escape HTML para exibição segura quando o backend não envia conteúdo já sanitizado
function escapeHtml(s) {
    if (s == null || s === '') return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Mostrar detalhes do resumo
function mostrarDetalhesResumo(resumo) {
    const conteudo = document.getElementById('conteudoDetalhes');
    const analise = JSON.parse(resumo.analise_ia || '{}');
    const notaAtual = resumo.nota !== null && resumo.nota !== undefined ? parseFloat(resumo.nota) : '';
    const observacoesAtuais = resumo.observacoes_professor || '';
    const textoResumoDisplay = (resumo.resumo_texto_display !== undefined && resumo.resumo_texto_display !== '')
        ? resumo.resumo_texto_display
        : escapeHtml(resumo.resumo_texto || resumo.resumo_aluno || 'Aluno ainda não entregou o resumo.');
    
    conteudo.innerHTML = `
        <div class="space-y-6">
            <!-- Informações do Aluno -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-blue-900 mb-2">Informações do Aluno</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-blue-700">Nome:</span>
                        <span class="text-blue-600">${resumo.nome_aluno}</span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Aula:</span>
                        <span class="text-blue-600">${resumo.nome_aula}</span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Data de Entrega:</span>
                        <span class="text-blue-600">${resumo.created_at ? new Date(resumo.created_at).toLocaleString('pt-BR') : 'Não entregue'}</span>
                    </div>
                    <div>
                        <span class="font-medium text-blue-700">Status:</span>
                        <span class="text-blue-600">${resumo.resumo_texto ? 'Entregue' : 'Pendente'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Resumo do Aluno (formatado como o aluno escreveu: HTML sanitizado ou texto com quebras) -->
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Resumo do Aluno</h4>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 prose prose-sm max-w-none text-gray-700">
                    ${textoResumoDisplay}
                </div>
            </div>
            
            <!-- Análise da IA -->
            ${analise.pontuacao ? `
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Análise da IA</h4>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 space-y-4">
                        <!-- Pontuação -->
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-purple-700">Pontuação:</span>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <span class="text-lg font-bold text-purple-800">${analise.pontuacao}/10</span>
                            </div>
                        </div>
                        
                        <!-- Nível de Compreensão -->
                        <div>
                            <span class="font-medium text-purple-700">Nível de Compreensão:</span>
                            <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium
                                ${analise.nivel_compreensao === 'avançado' ? 'bg-green-100 text-green-800' : 
                                  analise.nivel_compreensao === 'intermediário' ? 'bg-blue-100 text-blue-800' : 
                                  'bg-yellow-100 text-yellow-800'}">
                                ${analise.nivel_compreensao}
                            </span>
                        </div>
                        
                        <!-- Pontos Acertados -->
                        ${analise.pontos_acertados && analise.pontos_acertados.length > 0 ? `
                            <div>
                                <h5 class="font-medium text-green-700 mb-2">Pontos Acertados:</h5>
                                <ul class="text-sm text-green-600 space-y-1">
                                    ${analise.pontos_acertados.map(ponto => `<li>• ${ponto}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                        
                        <!-- Lacunas Identificadas -->
                        ${analise.lacunas_identificadas && analise.lacunas_identificadas.length > 0 ? `
                            <div>
                                <h5 class="font-medium text-red-700 mb-2">Lacunas Identificadas:</h5>
                                <ul class="text-sm text-red-600 space-y-1">
                                    ${analise.lacunas_identificadas.map(lacuna => `<li>• ${lacuna}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                        
                        <!-- Sugestões de Melhoria -->
                        ${analise.sugestoes_melhoria && analise.sugestoes_melhoria.length > 0 ? `
                            <div>
                                <h5 class="font-medium text-blue-700 mb-2">Sugestões de Melhoria:</h5>
                                <ul class="text-sm text-blue-600 space-y-1">
                                    ${analise.sugestoes_melhoria.map(sugestao => `<li>• ${sugestao}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                    </div>
                </div>
            ` : ''}
            
            <!-- Avaliação do Professor -->
            <div>
                <h4 class="font-medium text-gray-900 mb-2">Avaliação do Professor</h4>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 space-y-4">
                    <form id="formAvaliarResumo" onsubmit="salvarNotaResumo(event, ${resumo.id})">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="resumo_id" value="${resumo.id}">
                        
                        <div>
                            <label for="notaResumo" class="block text-sm font-medium text-gray-700 mb-2">
                                Nota (0 a 10):
                            </label>
                            <input type="number" 
                                   id="notaResumo" 
                                   name="nota" 
                                   min="0" 
                                   max="10" 
                                   step="0.1"
                                   value="${notaAtual}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                        </div>
                        
                        <div>
                            <label for="observacoesResumo" class="block text-sm font-medium text-gray-700 mb-2">
                                Observações:
                            </label>
                            <textarea id="observacoesResumo" 
                                      name="observacoes" 
                                      rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">${observacoesAtuais}</textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-2 pt-2">
                            <button type="button" 
                                    onclick="document.getElementById('modalDetalhes').classList.add('hidden')"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Salvar Nota
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
}

// Salvar nota do resumo
function salvarNotaResumo(event, resumoId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('<?= URL ?>/professor/jornadas/resumos/atribuir-nota', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Nota atribuída com sucesso!');
            // Recarrega os detalhes do resumo para atualizar a nota exibida
            verDetalhesResumo(resumoId);
        } else {
            alert('Erro ao salvar nota: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar nota. Tente novamente.');
    });
}

// Abrir modal automaticamente se houver resumo_id_especifico
<?php if (!empty($resumo_id_especifico) && !empty($resumo_especifico)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Abre o modal automaticamente
    verDetalhesResumo(<?= $resumo_id_especifico ?>);
});
<?php endif; ?>

// Gerar explicação complementar
function gerarExplicacaoComplementar(resumoId) {
    if (!confirm('Deseja gerar uma explicação complementar baseada nas lacunas identificadas?')) return;
    
    fetch('<?= URL ?>/professor/jornadas/gerar-explicacao-complementar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            '_token': <?= json_encode($csrf_token) ?>,
            'resumo_id': resumoId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarExplicacaoComplementar(data.explicacao);
            document.getElementById('modalExplicacao').classList.remove('hidden');
        } else {
            alert('Erro ao gerar explicação: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar explicação. Tente novamente.');
    });
}

// Mostrar explicação complementar
function mostrarExplicacaoComplementar(explicacao) {
    const conteudo = document.getElementById('conteudoExplicacao');
    conteudo.innerHTML = `
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <div class="flex items-center mb-3">
                <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                <h4 class="font-medium text-purple-900">Explicação Complementar Gerada pela IA</h4>
            </div>
            <div class="text-gray-700 whitespace-pre-wrap">${explicacao}</div>
        </div>
    `;
}
</script>
