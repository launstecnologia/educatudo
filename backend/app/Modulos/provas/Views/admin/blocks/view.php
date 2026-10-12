<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?= htmlspecialchars($bloco['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Visualização detalhada do bloco de provas
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/editar" 
               class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">
                Editar
            </a>
            <a href="<?= URL ?>/admin/provas" 
               class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Info Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center">
            <div class="flex-1">
                <p class="text-sm text-gray-600">Data da Prova</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?= date('d/m/Y', strtotime($bloco['data_prova'])) ?>
                </p>
            </div>
            <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center">
            <div class="flex-1">
                <p class="text-sm text-gray-600">Horário</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> - <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                </p>
            </div>
            <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center">
            <div class="flex-1">
                <p class="text-sm text-gray-600">Total de Provas</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?= count($bloco['provas'] ?? []) ?>
                </p>
            </div>
            <svg class="w-12 h-12 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
    </div>
</div>

<!-- Detalhes -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações do Bloco</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <p class="text-sm text-gray-600 mb-1">Status</p>
            <p>
                <?php if ($bloco['liberado']): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Liberado
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        Não Liberado
                    </span>
                <?php endif; ?>
            </p>
        </div>
        
        <div>
            <p class="text-sm text-gray-600 mb-1">Turma</p>
            <p class="text-gray-900">
                <?= $bloco['turma_nome'] ? htmlspecialchars($bloco['turma_nome']) : 'Todas as turmas' ?>
            </p>
        </div>
        
        <div>
            <p class="text-sm text-gray-600 mb-1">Criado por</p>
            <p class="text-gray-900"><?= htmlspecialchars($bloco['criado_por_nome']) ?></p>
        </div>
        
        <div>
            <p class="text-sm text-gray-600 mb-1">Data de Criação</p>
            <p class="text-gray-900"><?= date('d/m/Y H:i', strtotime($bloco['created_at'])) ?></p>
        </div>
    </div>
    
    <?php if ($bloco['descricao']): ?>
        <div class="mt-6">
            <p class="text-sm text-gray-600 mb-1">Descrição</p>
            <p class="text-gray-900"><?= nl2br(htmlspecialchars($bloco['descricao'])) ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Provas do Bloco -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Provas do Bloco</h3>
    
    <?php if (empty($bloco['provas'])): ?>
        <p class="text-gray-500">Nenhuma prova adicionada a este bloco.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($bloco['provas'] as $prova): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($prova['titulo']) ?></h4>
                            <p class="text-sm text-gray-600 mt-1">
                                <span class="font-medium"><?= htmlspecialchars($prova['materia_nome']) ?></span> - 
                                Prof. <?= htmlspecialchars($prova['professor_nome']) ?>
                            </p>
                            <?php if ($prova['tempo_limite']): ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    Tempo limite: <?= $prova['tempo_limite'] ?> minutos
                                </p>
                            <?php endif; ?>
                        </div>
                        <a href="<?= URL ?>/admin/provas/visualizar/<?= $prova['id'] ?>" 
                           class="text-blue-600 hover:text-blue-900 text-sm">
                            Ver detalhes →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

