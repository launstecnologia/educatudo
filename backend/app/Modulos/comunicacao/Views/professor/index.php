<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Chat com Alunos</h1>
            <p class="text-gray-600 mt-2">Inicie uma conversa ou continue de onde parou</p>
        </div>
    </div>
</div>

<?php
$alunosDisponiveis = $alunos_disponiveis ?? [];
$idsComConversa = [];
foreach ($conversas ?? [] as $conv) {
    $idsComConversa[(int) ($conv['aluno_id'] ?? 0)] = true;
}
?>

<!-- Alunos vinculados (iniciar conversa) -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <h2 class="text-xl font-semibold text-gray-900">Alunos das suas turmas</h2>
        <?php if (!empty($alunosDisponiveis)): ?>
        <input type="search"
               id="filtro-alunos-chat"
               placeholder="Buscar aluno ou turma..."
               class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
               autocomplete="off">
        <?php endif; ?>
    </div>

    <?php if (empty($alunosDisponiveis)): ?>
        <div class="text-center py-10">
            <p class="text-gray-500 font-medium">Nenhum aluno vinculado às suas turmas</p>
            <p class="text-sm text-gray-400 mt-2">Confira se suas turmas estão cadastradas no perfil do professor.</p>
        </div>
    <?php else: ?>
        <div id="lista-alunos-chat" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($alunosDisponiveis as $aluno): ?>
                <?php
                $alunoId = (int) ($aluno['id'] ?? 0);
                $temConversa = !empty($idsComConversa[$alunoId]);
                $busca = mb_strtolower(
                    trim(($aluno['nome'] ?? '') . ' ' . ($aluno['turma_nome'] ?? '') . ' ' . ($aluno['ra'] ?? '')),
                    'UTF-8'
                );
                ?>
                <a href="<?= URL ?>/professor/chat/<?= $alunoId ?>"
                   data-busca="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>"
                   class="aluno-chat-card flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-gray-200">
                    <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mr-4 shrink-0">
                        <span class="text-white font-semibold text-lg">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($aluno['nome'] ?? '?'), 0, 1))) ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-gray-900 truncate"><?= htmlspecialchars($aluno['nome'] ?? '') ?></h3>
                        <p class="text-sm text-gray-600 truncate">
                            <?= htmlspecialchars($aluno['turma_nome'] ?? 'Sem turma') ?>
                            <?php if (!empty($aluno['ra'])): ?>
                                · RA <?= htmlspecialchars($aluno['ra']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($temConversa): ?>
                            <span class="inline-block mt-1 text-xs text-purple-600 font-medium">Conversa existente</span>
                        <?php else: ?>
                            <span class="inline-block mt-1 text-xs text-gray-500">Iniciar conversa</span>
                        <?php endif; ?>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            <?php endforeach; ?>
        </div>
        <p id="filtro-alunos-vazio" class="hidden text-center text-sm text-gray-500 mt-4">Nenhum aluno encontrado com esse filtro.</p>
    <?php endif; ?>
</div>

<!-- Conversas Existentes -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Conversas recentes</h2>
    </div>
    <div class="p-6">
        <?php if (empty($conversas)): ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <p class="text-gray-500 text-lg font-medium">Nenhuma conversa ainda</p>
                <p class="text-sm text-gray-400 mt-2">Escolha um aluno acima para começar</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($conversas as $conversa): ?>
                    <a href="<?= URL ?>/professor/chat/<?= (int) $conversa['aluno_id'] ?>"
                       class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer <?= ($conversa['mensagens_nao_lidas'] ?? 0) > 0 ? 'border-l-4 border-purple-500' : '' ?>">
                        <div class="flex items-center flex-1 min-w-0">
                            <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mr-4 shrink-0">
                                <span class="text-white font-semibold text-lg">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($conversa['aluno_nome'] ?? '?'), 0, 1))) ?>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2 mb-1">
                                    <h3 class="font-medium text-gray-900 truncate"><?= htmlspecialchars($conversa['aluno_nome'] ?? '') ?></h3>
                                    <?php if (($conversa['mensagens_nao_lidas'] ?? 0) > 0): ?>
                                        <span class="px-2 py-0.5 bg-red-500 text-white text-xs rounded-full font-medium">
                                            <?= (int) $conversa['mensagens_nao_lidas'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center space-x-2 text-sm text-gray-600 mb-1">
                                    <?php if (!empty($conversa['turma_nome'])): ?>
                                        <span class="text-gray-500"><?= htmlspecialchars($conversa['turma_nome']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($conversa['aluno_ra'])): ?>
                                        <span class="text-gray-400">·</span>
                                        <span class="text-gray-500">RA: <?= htmlspecialchars($conversa['aluno_ra']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($conversa['ultima_mensagem'])): ?>
                                    <p class="text-sm text-gray-600 truncate">
                                        <?= htmlspecialchars(mb_substr($conversa['ultima_mensagem'], 0, 60)) ?><?= mb_strlen($conversa['ultima_mensagem']) > 60 ? '...' : '' ?>
                                    </p>
                                    <?php if (!empty($conversa['ultima_mensagem_data'])): ?>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?= date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) ?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-sm text-gray-400">Nenhuma mensagem ainda</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4 shrink-0">
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

<?php if (!empty($alunosDisponiveis)): ?>
<script>
(function () {
    var input = document.getElementById('filtro-alunos-chat');
    var vazio = document.getElementById('filtro-alunos-vazio');
    if (!input) return;
    input.addEventListener('input', function () {
        var q = (input.value || '').toLowerCase().trim();
        var cards = document.querySelectorAll('.aluno-chat-card');
        var visiveis = 0;
        cards.forEach(function (card) {
            var ok = !q || (card.getAttribute('data-busca') || '').indexOf(q) !== -1;
            card.classList.toggle('hidden', !ok);
            if (ok) visiveis++;
        });
        if (vazio) vazio.classList.toggle('hidden', visiveis > 0);
    });
})();
</script>
<?php endif; ?>
