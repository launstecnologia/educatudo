<?php
/**
 * View: Lista de Simulados ENEM
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">📚 Simulados ENEM</h1>
                <p class="text-gray-600">Pratique com questões reais do Exame Nacional do Ensino Médio</p>
            </div>
            <a href="<?= URL ?>/simulados/criar" 
               class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                <span class="mr-2">➕</span>
                Novo Simulado
            </a>
        </div>
    </div>

    <!-- Estatísticas Gerais -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <span class="text-2xl">📊</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total de Simulados</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $estatisticas['total_simulados'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <span class="text-2xl">✅</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Finalizados</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $estatisticas['simulados_finalizados'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-full">
                    <span class="text-2xl">🎯</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Média de Acertos</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $estatisticas['media_acertos'] ?>%</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-full">
                    <span class="text-2xl">🏆</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Nota Média</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $estatisticas['media_nota'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Simulados -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Meus Simulados</h2>
        </div>

        <?php if (empty($simulados)): ?>
            <div class="p-12 text-center">
                <div class="text-6xl mb-4">📝</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhum simulado encontrado</h3>
                <p class="text-gray-600 mb-6">Crie seu primeiro simulado para começar a praticar!</p>
                <a href="<?= URL ?>/simulados/criar" 
                   class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    Criar Primeiro Simulado
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($simulados as $simulado): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Simulado ENEM <?= $simulado['ano'] ?>
                                    </h3>
                                    <span class="ml-3 px-3 py-1 text-xs font-medium rounded-full
                                        <?php 
                                        switch($simulado['status']) {
                                            case 'criado': echo 'bg-gray-100 text-gray-800'; break;
                                            case 'em_andamento': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'finalizado': echo 'bg-green-100 text-green-800'; break;
                                            case 'cancelado': echo 'bg-red-100 text-red-800'; break;
                                        }
                                        ?>">
                                        <?php 
                                        switch($simulado['status']) {
                                            case 'criado': echo 'Criado'; break;
                                            case 'em_andamento': echo 'Em Andamento'; break;
                                            case 'finalizado': echo 'Finalizado'; break;
                                            case 'cancelado': echo 'Cancelado'; break;
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">Disciplina:</span> 
                                        <?= $simulado['disciplina'] ?: 'Todas' ?>
                                    </div>
                                    <div>
                                        <span class="font-medium">Questões:</span> 
                                        <?= $simulado['quantidade_questoes'] ?>
                                    </div>
                                    <div>
                                        <span class="font-medium">Criado em:</span> 
                                        <?= date('d/m/Y H:i', strtotime($simulado['criado_em'])) ?>
                                    </div>
                                    <?php if ($simulado['status'] === 'finalizado'): ?>
                                        <div>
                                            <span class="font-medium">Nota:</span> 
                                            <span class="font-bold text-green-600"><?= $simulado['nota_final'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($simulado['status'] === 'finalizado'): ?>
                                    <div class="mt-3 flex items-center space-x-4 text-sm">
                                        <span class="text-green-600 font-medium">
                                            ✅ <?= $simulado['total_acertos'] ?> acertos
                                        </span>
                                        <span class="text-red-600 font-medium">
                                            ❌ <?= $simulado['total_erros'] ?> erros
                                        </span>
                                        <span class="text-blue-600 font-medium">
                                            📊 <?= $simulado['percentual_acerto'] ?>% de acerto
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center space-x-3">
                                <?php if ($simulado['status'] === 'criado'): ?>
                                    <a href="<?= URL ?>/simulados/iniciar?id=<?= $simulado['id'] ?>" 
                                       class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                        Iniciar
                                    </a>
                                <?php elseif ($simulado['status'] === 'em_andamento'): ?>
                                    <a href="<?= URL ?>/simulados/iniciar?id=<?= $simulado['id'] ?>" 
                                       class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
                                        Continuar
                                    </a>
                                <?php elseif ($simulado['status'] === 'finalizado'): ?>
                                    <a href="<?= URL ?>/simulados/resultado?id=<?= $simulado['id'] ?>" 
                                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                                        Ver Resultado
                                    </a>
                                <?php endif; ?>
                                <button onclick="ocultarSimulado(<?= $simulado['id'] ?>)" 
                                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors">
                                    Remover
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Informações sobre Simulados -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-3">ℹ️ Sobre os Simulados ENEM</h3>
        <div class="text-blue-800 space-y-2">
            <p>• <strong>Questões Reais:</strong> Todas as questões são do banco oficial do ENEM</p>
            <p>• <strong>Controle de Tempo:</strong> Cronômetro automático para simular condições reais</p>
            <p>• <strong>Correção Instantânea:</strong> Veja seus resultados imediatamente após finalizar</p>
            <p>• <strong>Estatísticas Detalhadas:</strong> Acompanhe seu desempenho por matéria</p>
            <p>• <strong>Flexibilidade:</strong> Escolha ano, disciplina e quantidade de questões</p>
        </div>
    </div>
</div>

<script>
function ocultarSimulado(id) {
    if (confirm('Tem certeza que deseja remover este simulado da sua lista? Ele não será excluído permanentemente.')) {
        fetch(`<?= URL ?>/simulados/${id}/ocultar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar notificação de sucesso
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                notification.textContent = data.message || 'Simulado removido com sucesso!';
                document.body.appendChild(notification);
                
                // Fade out e remover após 3 segundos
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.5s ease-out';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 500);
                }, 3000);
                
                // Recarregar a página após 1 segundo
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                alert('Erro ao remover simulado: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao remover simulado.');
        });
    }
}
</script>
