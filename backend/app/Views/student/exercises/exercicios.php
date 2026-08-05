<?php
/**
 * View: Lista de Exercícios para Alunos com Dashboard e Paginação
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
            📚 Exercícios Interativos
        </h1>
        <p class="text-gray-600">
            Pratique seus conhecimentos com exercícios organizados por matéria e nível de dificuldade.
        </p>
    </div>

    <!-- Mensagens de Sucesso/Erro -->
    <?php if (isset($_GET['success'])): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        ✅ <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <!-- Dashboard de Estatísticas -->
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 Dashboard de Exercícios</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Disponível -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <span class="text-2xl">📚</span>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Disponível</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $dashboard_stats['total_disponivel'] ?? 0 ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Feito -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <span class="text-2xl">✅</span>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Feito</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $dashboard_stats['total_feito'] ?? 0 ?></p>
                    </div>
                </div>
            </div>

            <!-- Média de Acertos -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <span class="text-2xl">🎯</span>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Média de Acertos</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($dashboard_stats['media_acertos'] ?? 0, 1) ?>%</p>
                    </div>
                </div>
            </div>

            <!-- Média de Erros -->
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-full">
                        <span class="text-2xl">❌</span>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Média de Erros</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($dashboard_stats['media_erros'] ?? 0, 1) ?>%</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulário de Pesquisa -->
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">🔍 Pesquisar Exercícios</h2>
            
            <form method="GET" action="<?= URL ?>/exercicios" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Matéria -->
                <div>
                    <label for="materia" class="block text-sm font-medium text-gray-700 mb-2">📖 Matéria</label>
                    <input type="text" 
                           id="materia" 
                           name="materia" 
                           value="<?= htmlspecialchars($filtros['materia'] ?? '') ?>"
                           placeholder="Ex: Matemática"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Título -->
                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">📝 Título</label>
                    <input type="text" 
                           id="titulo" 
                           name="titulo" 
                           value="<?= htmlspecialchars($filtros['titulo'] ?? '') ?>"
                           placeholder="Ex: Equações"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Dificuldade -->
                <div>
                    <label for="dificuldade" class="block text-sm font-medium text-gray-700 mb-2">⚡ Dificuldade</label>
                    <select id="dificuldade" 
                            name="dificuldade" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas</option>
                        <option value="Fácil" <?= ($filtros['dificuldade'] ?? '') === 'Fácil' ? 'selected' : '' ?>>Fácil</option>
                        <option value="Médio" <?= ($filtros['dificuldade'] ?? '') === 'Médio' ? 'selected' : '' ?>>Médio</option>
                        <option value="Difícil" <?= ($filtros['dificuldade'] ?? '') === 'Difícil' ? 'selected' : '' ?>>Difícil</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">📊 Status</label>
                    <select id="status" 
                            name="status" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="nao_feito" <?= ($filtros['status'] ?? '') === 'nao_feito' ? 'selected' : '' ?>>⚪ Não Feito</option>
                        <option value="bloqueado" <?= ($filtros['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>>🔒 Bloqueado</option>
                        <option value="disponivel" <?= ($filtros['status'] ?? '') === 'disponivel' ? 'selected' : '' ?>>✅ Disponível</option>
                    </select>
                </div>

                <!-- Botões -->
                <div class="md:col-span-2 lg:col-span-4 flex gap-2">
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        🔍 Pesquisar
                    </button>
                    <a href="<?= URL ?>/exercicios" 
                       class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        🗑️ Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>


    <!-- Lista de Exercícios com Paginação -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">📋 Exercícios Encontrados</h2>
            <div class="text-sm text-gray-600">
                Página <?= $pagination['current_page'] ?> de <?= $pagination['total_pages'] ?> 
                (<?= $pagination['total_items'] ?> exercícios)
            </div>
        </div>

        <?php if (!empty($listas)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($listas as $lista): ?>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($lista['titulo']) ?></h3>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    $status_icon = '';
                    
                    switch ($lista['status_atual']) {
                        case 'nao_feito':
                            $status_class = 'bg-gray-100 text-gray-800';
                            $status_text = 'Não Feito';
                            $status_icon = '⚪';
                            break;
                        case 'bloqueado':
                            $status_class = 'bg-red-100 text-red-800';
                            $status_text = 'Bloqueado';
                            $status_icon = '🔒';
                            break;
                        case 'disponivel':
                            $status_class = 'bg-green-100 text-green-800';
                            $status_text = 'Disponível';
                            $status_icon = '✅';
                            break;
                    }
                    ?>
                    <span class="px-2 py-1 <?= $status_class ?> text-xs font-medium rounded-full">
                        <?= $status_icon ?> <?= $status_text ?>
                    </span>
                </div>
                
                <div class="space-y-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="mr-2">📖</span>
                        <span><?= htmlspecialchars($lista['materia']) ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="mr-2">⚡</span>
                        <span><?= htmlspecialchars($lista['nivel_dificuldade']) ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="mr-2">❓</span>
                        <span><?= $lista['total_questoes'] ?> questões</span>
                    </div>
                    
                    <?php if ($lista['ultima_execucao']): ?>
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="mr-2">📅</span>
                        <span>Última execução: <?= date('d/m/Y H:i', strtotime($lista['ultima_execucao'])) ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="mr-2">📊</span>
                        <span>Último resultado: <?= number_format($lista['ultimo_percentual'], 1) ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($lista['status_atual'] === 'nao_feito' || $lista['status_atual'] === 'disponivel'): ?>
                <div class="flex gap-2">
                    <a href="<?= URL ?>/exercicios/iniciar?id=<?= $lista['id'] ?>" 
                       class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors text-center">
                        🚀 <?= $lista['status_atual'] === 'nao_feito' ? 'Iniciar' : 'Refazer' ?> Exercício
                    </a>
                    <?php if ($lista['ultima_execucao']): ?>
                    <a href="<?= URL ?>/exercicios/historico?lista_id=<?= $lista['id'] ?>&sessao_id=<?= $lista['ultima_sessao_id'] ?? '' ?>" 
                       class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-center">
                        📚 Histórico
                    </a>
                    <?php endif; ?>
                </div>
                <?php elseif ($lista['status_atual'] === 'bloqueado'): ?>
                <div class="flex gap-2">
                    <div class="flex-1 bg-gray-400 text-white py-2 px-4 rounded-lg text-center">
                        🔒 Bloqueado por 24h
                    </div>
                    <?php if ($lista['ultima_execucao']): ?>
                    <a href="<?= URL ?>/exercicios/historico?lista_id=<?= $lista['id'] ?>&sessao_id=<?= $lista['ultima_sessao_id'] ?? '' ?>" 
                       class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-center">
                        📚 Histórico
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="flex items-center justify-center mt-8 space-x-2">
            <?php 
            // Construir query string com filtros
            $query_params = [];
            if (!empty($filtros['materia'])) $query_params['materia'] = $filtros['materia'];
            if (!empty($filtros['titulo'])) $query_params['titulo'] = $filtros['titulo'];
            if (!empty($filtros['dificuldade'])) $query_params['dificuldade'] = $filtros['dificuldade'];
            if (!empty($filtros['status'])) $query_params['status'] = $filtros['status'];
            
            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
            ?>
            
            <?php if ($pagination['has_prev']): ?>
            <a href="<?= URL ?>/exercicios?page=<?= $pagination['prev_page'] ?><?= $query_string ?>" 
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                ← Anterior
            </a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $pagination['current_page'] - 2);
            $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
            <a href="<?= URL ?>/exercicios?page=<?= $i ?><?= $query_string ?>" 
               class="px-4 py-2 <?= $i === $pagination['current_page'] ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded-lg transition-colors">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagination['has_next']): ?>
            <a href="<?= URL ?>/exercicios?page=<?= $pagination['next_page'] ?><?= $query_string ?>" 
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Próximo →
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-12">
            <div class="text-gray-400 text-6xl mb-4">📚</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum exercício disponível</h3>
            <p class="text-gray-600">Não há exercícios cadastrados no momento.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
