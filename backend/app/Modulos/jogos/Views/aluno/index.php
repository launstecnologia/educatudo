<?php
/**
 * EducaTudo - Página Principal de Jogos
 * Interface com cards dos jogos disponíveis
 */

$stats = $data['stats'] ?? [];
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                🎮 Jogos Educativos
            </h2>
            <p class="text-gray-600">
                Divirta-se aprendendo com nossos jogos interativos!
            </p>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-500">Seu nível atual</div>
            <div class="text-2xl font-bold text-blue-600">
                <?= htmlspecialchars($stats['nivel_atual'] ?? 'Iniciante') ?>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas do Jogador -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Partidas Jogadas</p>
                <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_partidas'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Vitórias</p>
                <p class="text-2xl font-semibold text-gray-900"><?= $stats['partidas_vencidas'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 rounded-lg">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Ganho</p>
                <p class="text-2xl font-semibold text-gray-900">R$ <?= number_format($stats['total_premio'] ?? 0, 2, ',', '.') ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Maior Prêmio</p>
                <p class="text-2xl font-semibold text-gray-900">R$ <?= number_format($stats['maior_premio'] ?? 0, 2, ',', '.') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Cards dos Jogos -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    
    <!-- Jogo do Milhão -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
        <div class="bg-gradient-to-r from-yellow-400 to-yellow-600 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">🎯 Jogo do Milhão</h3>
                    <p class="text-yellow-100 text-sm">Universidade</p>
                </div>
                <div class="text-right">
                    <div class="text-white text-sm">Prêmio máximo</div>
                    <div class="text-white font-bold text-lg">R$ 1.000.000</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Teste seus conhecimentos respondendo 15 perguntas progressivas. 
                Use suas ajudas especiais e ganhe até R$ 1.000.000!
            </p>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    15 perguntas progressivas
                </div>
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    3 ajudas especiais
                </div>
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Perguntas aleatórias
                </div>
            </div>
            
            <a href="<?= URL ?>/jogo-milhao" 
               class="w-full bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors text-center block font-semibold">
                🎮 Jogar Agora
            </a>
        </div>
    </div>
    
    <!-- Jogo de Xadrez (Em Breve) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden opacity-75">
        <div class="bg-gradient-to-r from-gray-400 to-gray-600 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">♟️ Xadrez Educativo</h3>
                    <p class="text-gray-200 text-sm">Estratégia</p>
                </div>
                <div class="text-right">
                    <div class="text-white text-sm">Em breve</div>
                    <div class="text-white font-bold text-lg">-</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Aprenda estratégias de xadrez enquanto se diverte. 
                Desafie outros alunos e melhore suas habilidades!
            </p>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Modo multiplayer
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Tutoriais interativos
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Ranking de jogadores
                </div>
            </div>
            
            <button disabled 
                    class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed text-center font-semibold">
                🚧 Em Breve
            </button>
        </div>
    </div>
    
    <!-- Jogo da Dama (Desabilitado) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden opacity-50">
        <div class="bg-gradient-to-r from-gray-400 to-gray-500 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">🔴 Jogo da Dama</h3>
                    <p class="text-gray-200 text-sm">vs Tudinha</p>
                </div>
                <div class="text-right">
                    <div class="text-gray-200 text-sm">Em Breve</div>
                    <div class="text-gray-200 font-bold text-lg">🚧</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-500 mb-4">
                Este jogo está em desenvolvimento e estará disponível em breve!
            </p>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    3 níveis de dificuldade
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    IA inteligente
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Estatísticas detalhadas
                </div>
            </div>
            
            <button disabled 
                    class="w-full bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed text-center block font-semibold">
                🚧 Em Breve
            </button>
        </div>
    </div>
    
    <!-- Quiz Rápido (Em Breve) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden opacity-75">
        <div class="bg-gradient-to-r from-blue-400 to-blue-600 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">⚡ Quiz Rápido</h3>
                    <p class="text-blue-100 text-sm">Conhecimento</p>
                </div>
                <div class="text-right">
                    <div class="text-white text-sm">Em breve</div>
                    <div class="text-white font-bold text-lg">-</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Teste seus conhecimentos em quizzes rápidos de 5 minutos. 
                Perfeito para revisar matérias antes das provas!
            </p>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Quiz de 5 minutos
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Por matéria
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Ranking diário
                </div>
            </div>
            
            <button disabled 
                    class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed text-center font-semibold">
                🚧 Em Breve
            </button>
        </div>
    </div>
    
    <!-- Palavras Cruzadas (Em Breve) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden opacity-75">
        <div class="bg-gradient-to-r from-green-400 to-green-600 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">📝 Palavras Cruzadas</h3>
                    <p class="text-green-100 text-sm">Vocabulário</p>
                </div>
                <div class="text-right">
                    <div class="text-white text-sm">Em breve</div>
                    <div class="text-white font-bold text-lg">-</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Desafie seu vocabulário com palavras cruzadas educativas. 
                Aprenda novas palavras enquanto se diverte!
            </p>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Temas variados
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Dicas inteligentes
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Progresso salvo
                </div>
            </div>
            
            <button disabled 
                    class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed text-center font-semibold">
                🚧 Em Breve
            </button>
        </div>
    </div>
    
    <!-- Memória Educativa (Em Breve) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden opacity-75">
        <div class="bg-gradient-to-r from-purple-400 to-purple-600 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-white">🧠 Memória Educativa</h3>
                    <p class="text-purple-100 text-sm">Concentração</p>
                </div>
                <div class="text-right">
                    <div class="text-white text-sm">Em breve</div>
                    <div class="text-white font-bold text-lg">-</div>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">
                Treine sua memória com cartas educativas. 
                Combine imagens, palavras e conceitos enquanto se diverte!
            </p>
            
            <div class="space-y-2 mb-4">
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Diferentes temas
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Níveis de dificuldade
                </div>
                <div class="flex items-center text-sm text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Tempo cronometrado
                </div>
            </div>
            
            <button disabled 
                    class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed text-center font-semibold">
                🚧 Em Breve
            </button>
        </div>
    </div>
</div>

<!-- Seção de Conquistas -->
<div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">🏆 Suas Conquistas</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="text-2xl mb-2">🎯</div>
                <h4 class="font-semibold text-gray-900">Primeiro Milhão</h4>
                <p class="text-sm text-gray-600">Ganhe R$ 1.000.000 no Jogo do Milhão</p>
                <div class="mt-2">
                    <?php if (($stats['maior_premio'] ?? 0) >= 1000000): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            ✅ Conquistado
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            🔒 Em progresso
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="text-2xl mb-2">🎮</div>
                <h4 class="font-semibold text-gray-900">Jogador Dedicado</h4>
                <p class="text-sm text-gray-600">Jogue 10 partidas</p>
                <div class="mt-2">
                    <?php if (($stats['total_partidas'] ?? 0) >= 10): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            ✅ Conquistado
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            🔒 <?= ($stats['total_partidas'] ?? 0) ?>/10
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="text-2xl mb-2">🏅</div>
                <h4 class="font-semibold text-gray-900">Campeão</h4>
                <p class="text-sm text-gray-600">Vença 5 partidas</p>
                <div class="mt-2">
                    <?php if (($stats['partidas_vencidas'] ?? 0) >= 5): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            ✅ Conquistado
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            🔒 <?= ($stats['partidas_vencidas'] ?? 0) ?>/5
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
