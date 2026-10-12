<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Chat com Alunos 💬</h1>
            <p class="text-gray-600 mt-2">Converse com seus alunos e responda suas dúvidas</p>
        </div>
    </div>
</div>

<!-- Conversas Existentes -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Conversas</h2>
    </div>
    <div class="p-6">
        <?php if (empty($conversas)): ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <p class="text-gray-500 text-lg font-medium">Nenhuma conversa ainda</p>
                <p class="text-sm text-gray-400 mt-2">As conversas com seus alunos aparecerão aqui</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($conversas as $conversa): ?>
                    <a href="<?= URL ?>/professor/chat/<?= $conversa['aluno_id'] ?>" 
                       class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer <?= ($conversa['mensagens_nao_lidas'] ?? 0) > 0 ? 'border-l-4 border-purple-500' : '' ?>">
                        <div class="flex items-center flex-1">
                            <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mr-4">
                                <span class="text-white font-semibold text-lg">
                                    <?= mb_substr($conversa['aluno_nome'], 0, 1) ?>
                                </span>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-1">
                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($conversa['aluno_nome']) ?></h3>
                                    <?php if (($conversa['mensagens_nao_lidas'] ?? 0) > 0): ?>
                                        <span class="px-2 py-0.5 bg-red-500 text-white text-xs rounded-full font-medium">
                                            <?= $conversa['mensagens_nao_lidas'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center space-x-2 text-sm text-gray-600 mb-1">
                                    <?php if (!empty($conversa['turma_nome'])): ?>
                                        <span class="text-gray-500"><?= htmlspecialchars($conversa['turma_nome']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($conversa['aluno_ra'])): ?>
                                        <span class="text-gray-400">•</span>
                                        <span class="text-gray-500">RA: <?= htmlspecialchars($conversa['aluno_ra']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($conversa['ultima_mensagem']): ?>
                                    <p class="text-sm text-gray-600 truncate">
                                        <?= htmlspecialchars(mb_substr($conversa['ultima_mensagem'], 0, 60)) ?><?= mb_strlen($conversa['ultima_mensagem']) > 60 ? '...' : '' ?>
                                    </p>
                                    <?php if ($conversa['ultima_mensagem_data']): ?>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?= date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) ?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-sm text-gray-400">Nenhuma mensagem ainda</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

