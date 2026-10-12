<?php
$reports_filter_tab = 'dashboard';
$reports_filter_jornadas_extended = false;
require __DIR__ . '/_reports_filters_form.php';
?>
<!-- Cards de Quantidade -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Card de Interações -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-medium text-purple-100 mb-1">Total de Interações</h3>
                <p class="text-3xl font-bold"><?= number_format($chat_stats['total_interacoes'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
        </div>
        <div class="flex items-center text-sm text-purple-100">
            <span class="mr-2">💬</span>
            <span>Interações de alunos no chat</span>
        </div>
    </div>

    <!-- Card de Exercícios BD -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-medium text-blue-100 mb-1">Exercícios BD</h3>
                <p class="text-3xl font-bold"><?= number_format($exercises_stats['total_execucoes_bd'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
            </div>
        </div>
        <div class="flex items-center text-sm text-blue-100">
            <span class="mr-2">📚</span>
            <span>Exercícios do banco de dados</span>
        </div>
    </div>

    <!-- Card de Exercícios IA -->
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-medium text-indigo-100 mb-1">Exercícios IA</h3>
                <p class="text-3xl font-bold"><?= number_format($exercises_stats['total_execucoes_ia'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
            </div>
        </div>
        <div class="flex items-center text-sm text-indigo-100">
            <span class="mr-2">🤖</span>
            <span>Exercícios gerados por IA</span>
        </div>
    </div>

    <!-- Card de Redações -->
    <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-lg shadow-lg p-6 text-white">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-medium text-red-100 mb-1">Total de Redações</h3>
                <p class="text-3xl font-bold"><?= number_format($essays_stats['total_redacoes'] ?? 0, 0, ',', '.') ?></p>
            </div>
            <div class="bg-white bg-opacity-20 rounded-full p-3">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </div>
        </div>
        <div class="flex items-center text-sm text-red-100">
            <span class="mr-2">✍️</span>
            <span>Redações realizadas</span>
        </div>
    </div>
</div>

<!-- Gráficos de Evolução Temporal -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Gráfico de Chat ao Longo do Tempo -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            💬 Evolução de Interações de Chat
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="chatTemporalChart"></canvas>
        </div>
    </div>
    
    <!-- Gráfico de Exercícios ao Longo do Tempo -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            📝 Evolução de Exercícios
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="exercisesTemporalChart"></canvas>
        </div>
    </div>
    
    <!-- Gráfico de Redações ao Longo do Tempo -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 lg:col-span-2">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            ✍️ Evolução de Redações
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="essaysTemporalChart"></canvas>
        </div>
    </div>
</div>

<!-- Gráficos de Distribuição por Turma (apenas se tipo = geral) -->
<?php if ($filtros['tipo'] === 'geral'): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Distribuição de Chat por Turma -->
    <?php if (!empty($chat_stats['interacoes_por_turma'])): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            💬 Chat por Turma
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="chatTurmaChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Distribuição de Exercícios por Turma -->
    <?php if (!empty($exercises_stats['stats_por_turma'])): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            📝 Exercícios por Turma
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="exercisesTurmaChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Distribuição de Redações por Turma -->
    <?php if (!empty($essays_stats['stats_por_turma'])): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 lg:col-span-2">
        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            ✍️ Redações por Turma
        </h3>
        <div style="position: relative; height: 300px;">
            <canvas id="essaysTurmaChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Gráfico Comparativo Geral -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        📊 Visão Comparativa Geral
    </h3>
    <div style="position: relative; height: 300px;">
        <canvas id="comparativeChart"></canvas>
    </div>
</div>

