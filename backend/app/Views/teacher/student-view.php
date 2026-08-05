<!-- Header Section -->
<div class="mb-6 md:mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex-1">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Detalhes do Aluno 👤</h1>
            <p class="text-sm md:text-base text-gray-600 mt-2">Visualizar informações detalhadas sobre este aluno</p>
        </div>
        <div class="flex items-center space-x-4">
            <a href="<?= URL ?>/professor/student" class="px-3 md:px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm md:text-base">
                ← Voltar aos Alunos
            </a>
        </div>
    </div>
</div>

<!-- Student Info Card -->
<div class="bg-white rounded-xl shadow-lg p-4 md:p-6 mb-4 md:mb-6">
    <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4 md:gap-6">
        <!-- Avatar e Informações Básicas -->
        <div class="flex items-center space-x-4 flex-shrink-0">
            <div class="w-24 h-24 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-3xl">
                    <?= strtoupper(substr($aluno['nome'] ?? '', 0, 2)) ?>
                </span>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($aluno['nome'] ?? '') ?></h2>
                <p class="text-gray-600 mb-3"><?= htmlspecialchars($aluno['email'] ?? '') ?></p>
                <div class="flex items-center space-x-2">
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                        <?= htmlspecialchars($aluno['turma_nome'] ?? '') ?>
                    </span>
                    <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                        Aluno Ativo
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Métricas -->
        <div class="flex-1 w-full lg:w-auto">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <!-- Total Jornadas -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
                    <div class="flex items-center justify-between mb-2">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <p class="text-xs text-purple-700 font-medium mb-1">Total Jornadas</p>
                    <p class="text-2xl font-bold text-purple-900"><?= $total_jornadas ?? 0 ?></p>
                </div>
                
                <!-- Jornadas Concluídas -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
                    <div class="flex items-center justify-between mb-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-xs text-green-700 font-medium mb-1">Concluídas</p>
                    <p class="text-2xl font-bold text-green-900"><?= $jornadas_concluidas ?? 0 ?></p>
                </div>
                
                <!-- Jornadas Pendentes -->
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg p-4 border border-yellow-200">
                    <div class="flex items-center justify-between mb-2">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-xs text-yellow-700 font-medium mb-1">Pendentes</p>
                    <p class="text-2xl font-bold text-yellow-900"><?= $jornadas_pendentes ?? 0 ?></p>
                </div>
                
                <!-- Mensagens -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center justify-between mb-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <p class="text-xs text-blue-700 font-medium mb-1">Mensagens</p>
                    <p class="text-2xl font-bold text-blue-900"><?= $mensagens_prof ?? 0 ?></p>
                </div>
                
                <!-- Nota Última Prova -->
                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-lg p-4 border border-indigo-200">
                    <div class="flex items-center justify-between mb-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </div>
                    <p class="text-xs text-indigo-700 font-medium mb-1">Última Prova</p>
                    <p class="text-2xl font-bold text-indigo-900">
                        <?= $nota_ultima_prova !== null ? number_format($nota_ultima_prova, 1) : '-' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Details Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
    <!-- Personal Information -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações Pessoais</h3>
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-gray-500">Nome Completo:</span>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($aluno['nome'] ?? '') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Email:</span>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($aluno['email'] ?? '') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Turma:</span>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($aluno['turma_nome'] ?? '') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Status:</span>
                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                    Ativo
                </span>
            </div>
        </div>
    </div>

    <!-- Academic Information -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações Acadêmicas</h3>
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-gray-500">Total de Jornadas:</span>
                <span class="font-medium text-gray-900"><?= $total_jornadas ?? 0 ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="mt-4 md:mt-6 bg-white rounded-xl shadow-lg p-4 md:p-6">
    <h3 class="text-base md:text-lg font-semibold text-gray-900 mb-3 md:mb-4">Ações Disponíveis</h3>
    <div class="flex flex-col sm:flex-row flex-wrap gap-3 md:gap-4">
        <a href="<?= URL ?>/professor/student/<?= $aluno['id'] ?>/provas" class="px-4 md:px-6 py-2 md:py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors inline-flex items-center justify-center text-sm md:text-base">
            <svg class="w-4 h-4 md:w-5 md:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Ver Provas Semanais
        </a>
        
        <a href="<?= URL ?>/professor/student/<?= $aluno['id'] ?>/relatorio" class="px-4 md:px-6 py-2 md:py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors inline-flex items-center justify-center text-sm md:text-base">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Gerar Relatório
        </a>
    </div>
</div>

