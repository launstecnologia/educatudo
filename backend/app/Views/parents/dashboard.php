<!-- Welcome Section -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">
        Olá, <?= htmlspecialchars($pai['nome'] ?? $user['nome'] ?? '') ?>! 👨‍👩‍👧‍👦
    </h2>
    <p class="text-gray-600">
        Acompanhe o progresso e desempenho dos seus filhos na escola.
    </p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Filhos</p>
                <p class="text-2xl font-bold text-gray-900"><?= $stats['total_filhos'] ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Jornadas</p>
                <p class="text-2xl font-bold text-gray-900"><?= $stats['total_jornadas'] ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Exercícios</p>
                <p class="text-2xl font-bold text-gray-900"><?= $stats['total_exercicios'] ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-2 bg-orange-100 rounded-lg">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Redações</p>
                <p class="text-2xl font-bold text-gray-900"><?= $stats['total_redacoes'] ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Children List -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Meus Filhos</h3>
            <a href="<?= URL ?>/pais/filhos" class="text-blue-600 hover:text-blue-500 text-sm font-medium">
                Ver detalhes
            </a>
        </div>
    </div>
    <div class="p-6">
        <?php if (empty($filhos)): ?>
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <p class="text-gray-500">Nenhum filho cadastrado</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($filhos as $f): ?>
                    <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <span class="text-lg font-medium text-purple-600">
                                    <?= strtoupper(substr($f['nome'] ?? '', 0, 2)) ?>
                                </span>
                            </div>
                            <div class="ml-3">
                                <h4 class="font-medium text-gray-900"><?= htmlspecialchars($f['nome'] ?? '') ?></h4>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($f['turma_nome'] ?? '') ?></p>
                            </div>
                        </div>
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">RA:</span>
                                <span class="font-medium"><?= htmlspecialchars($f['ra'] ?? '') ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Status:</span>
                                <span class="inline-block bg-green-100 text-green-600 text-xs px-2 py-1 rounded">Ativo</span>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <a href="<?= URL ?>/pais/filhos/<?= $f['id'] ?>" class="flex-1 bg-purple-600 text-white text-center py-2 rounded text-sm hover:bg-purple-700 transition-colors">Ver Detalhes</a>
                            <a href="<?= URL ?>/pais/filhos/<?= $f['id'] ?>/notas" class="flex-1 bg-blue-600 text-white text-center py-2 rounded text-sm hover:bg-blue-700 transition-colors">Notas</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="<?= URL ?>/pais/filhos" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path></svg>
        </div>
        <h3 class="font-medium text-gray-900">Meus Filhos</h3>
        <p class="text-sm text-gray-500">Ver todos os filhos</p>
    </a>
    <a href="<?= URL ?>/pais/mensagens" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
        </div>
        <h3 class="font-medium text-gray-900">Mensagens</h3>
        <p class="text-sm text-gray-500">Comunicação com escola</p>
    </a>
    <a href="<?= URL ?>/pais/notificacoes" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4.828 7l2.586 2.586a2 2 0 002.828 0L12 7H4.828z"></path></svg>
        </div>
        <h3 class="font-medium text-gray-900">Notificações</h3>
        <p class="text-sm text-gray-500">Avisos importantes</p>
    </a>
    <a href="<?= $filho ? URL . '/pais/filhos/' . $filho['id'] . '/relatorios' : URL . '/pais/filhos' ?>" class="bg-white rounded-lg shadow-sm p-6 text-center hover:shadow-md transition-shadow border border-gray-200">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        </div>
        <h3 class="font-medium text-gray-900">Relatórios</h3>
        <p class="text-sm text-gray-500">Relatórios dos filhos</p>
    </a>
</div>
