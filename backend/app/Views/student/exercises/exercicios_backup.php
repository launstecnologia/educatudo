<?php
/**
 * View: Lista de Exercícios para Alunos
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

    <!-- Estatísticas do Aluno -->
    <?php if (!empty($estatisticas)): ?>
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 Suas Estatísticas</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($estatisticas as $stat): ?>
            <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($stat['materia']) ?></h3>
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                        <?= htmlspecialchars($stat['serie']) ?>
                    </span>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Exercícios:</span>
                        <span class="font-medium"><?= $stat['total_exercicios'] ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Acertos:</span>
                        <span class="font-medium"><?= $stat['total_acertos'] ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Média:</span>
                        <span class="font-medium text-green-600"><?= number_format($stat['percentual_medio'], 1) ?>%</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tempo:</span>
                        <span class="font-medium"><?= gmdate('H:i:s', $stat['total_tempo'] ?: 0) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow-md p-4 border border-gray-200">
            <h3 class="font-semibold text-gray-900 mb-3">🔍 Filtrar Exercícios</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
                    <select id="filtroMateria" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas as matérias</option>
                        <option value="Matemática">Matemática</option>
                        <option value="Português">Português</option>
                        <option value="História">História</option>
                        <option value="Geografia">Geografia</option>
                        <option value="Física">Física</option>
                        <option value="Química">Química</option>
                        <option value="Biologia">Biologia</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nível</label>
                    <select id="filtroNivel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos os níveis</option>
                        <option value="Fácil">Fácil</option>
                        <option value="Médio">Médio</option>
                        <option value="Difícil">Difícil</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="filtroStatus" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="nao_iniciado">Não iniciado</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="finalizado">Finalizado</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Exercícios -->
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">📝 Exercícios Disponíveis</h2>
        
        <?php if (empty($listas)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center border border-gray-200">
            <div class="text-gray-400 text-6xl mb-4">📚</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum exercício disponível</h3>
            <p class="text-gray-600">Aguarde enquanto seus professores criam novos exercícios para você!</p>
        </div>
        <?php else: ?>
        <div id="listaExercicios" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($listas as $lista): ?>
            <div class="exercicio-card bg-white rounded-lg shadow-md border border-gray-200 hover:shadow-lg transition-shadow duration-200" 
                 data-materia="<?= htmlspecialchars($lista['materia']) ?>"
                 data-nivel="<?= htmlspecialchars($lista['nivel_dificuldade']) ?>"
                 data-status="<?= $lista['status_execucao'] ?: 'nao_iniciado' ?>">
                
                <!-- Header do Card -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                <?= htmlspecialchars($lista['titulo']) ?>
                            </h3>
                            <p class="text-sm text-gray-600">
                                <?= htmlspecialchars($lista['materia']) ?> • <?= htmlspecialchars($lista['serie']) ?>
                            </p>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            <?php 
                            switch($lista['nivel_dificuldade']) {
                                case 'Fácil': echo 'bg-green-100 text-green-800'; break;
                                case 'Médio': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'Difícil': echo 'bg-red-100 text-red-800'; break;
                            }
                            ?>">
                            <?= htmlspecialchars($lista['nivel_dificuldade']) ?>
                        </span>
                    </div>
                    
                    <?php if ($lista['descricao']): ?>
                    <p class="text-sm text-gray-600 mb-3">
                        <?= htmlspecialchars($lista['descricao']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Informações do Exercício -->
                <div class="p-6">
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Questões:</span>
                            <span class="font-medium"><?= $lista['total_questoes'] ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Criado por:</span>
                            <span class="font-medium"><?= htmlspecialchars($lista['criado_por_nome'] ?? 'Sistema') ?></span>
                        </div>
                        
                        <?php if ($lista['status_execucao']): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium 
                                <?php 
                                switch($lista['status_execucao']) {
                                    case 'em_andamento': echo 'text-yellow-600'; break;
                                    case 'finalizado': echo 'text-green-600'; break;
                                }
                                ?>">
                                <?php 
                                switch($lista['status_execucao']) {
                                    case 'em_andamento': echo 'Em andamento'; break;
                                    case 'finalizado': echo 'Finalizado'; break;
                                }
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($lista['status_execucao'] === 'finalizado' && $lista['percentual_acerto'] !== null): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Nota:</span>
                            <span class="font-medium text-green-600">
                                <?= number_format($lista['percentual_acerto'], 1) ?>%
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Botão de Ação -->
                    <div class="mt-4">
                        <?php if ($lista['status_execucao'] === 'em_andamento'): ?>
                        <a href="<?= URL ?>/exercicios/iniciar?id=<?= $lista['id'] ?>" 
                           class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors duration-200 text-center block">
                            ▶️ Continuar Exercício
                        </a>
                        <?php elseif ($lista['status_execucao'] === 'finalizado'): ?>
                        <a href="<?= URL ?>/exercicios/resultado?id=<?= $lista['execucao_id'] ?>" 
                           class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors duration-200 text-center block">
                            📊 Ver Resultado
                        </a>
                        <?php else: ?>
                        <a href="<?= URL ?>/exercicios/iniciar?id=<?= $lista['id'] ?>" 
                           class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 text-center block">
                            🚀 Iniciar Exercício
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroMateria = document.getElementById('filtroMateria');
    const filtroNivel = document.getElementById('filtroNivel');
    const filtroStatus = document.getElementById('filtroStatus');
    const listaExercicios = document.getElementById('listaExercicios');
    
    function filtrarExercicios() {
        const materia = filtroMateria.value;
        const nivel = filtroNivel.value;
        const status = filtroStatus.value;
        
        const cards = listaExercicios.querySelectorAll('.exercicio-card');
        
        cards.forEach(card => {
            const cardMateria = card.dataset.materia;
            const cardNivel = card.dataset.nivel;
            const cardStatus = card.dataset.status;
            
            let mostrar = true;
            
            if (materia && cardMateria !== materia) mostrar = false;
            if (nivel && cardNivel !== nivel) mostrar = false;
            if (status && cardStatus !== status) mostrar = false;
            
            card.style.display = mostrar ? 'block' : 'none';
        });
    }
    
    filtroMateria.addEventListener('change', filtrarExercicios);
    filtroNivel.addEventListener('change', filtrarExercicios);
    filtroStatus.addEventListener('change', filtrarExercicios);
});
</script>
