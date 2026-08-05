<?php
/**
 * Sidebar de sessões do chat apostila_ia (Fase B).
 * Variáveis: $sessoes, $sessao_ativa, $chat_base_url, $csrf_token, $nova_sessao_url
 */
$sessaoAtivaId = (int)($sessao_ativa['id'] ?? 0);
?>
<aside class="w-56 shrink-0 border-r border-gray-100 pr-3 flex flex-col min-h-0">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Conversas</h2>
        <form method="POST" action="<?= htmlspecialchars((string)$nova_sessao_url) ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf_token) ?>">
            <button type="submit" class="text-xs px-2 py-1 rounded-md bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-medium" title="Nova conversa">+ Nova</button>
        </form>
    </div>
    <div class="flex-1 overflow-y-auto space-y-1 min-h-0">
        <?php if (empty($sessoes)): ?>
            <p class="text-xs text-gray-400 px-1">Nenhuma conversa ainda.</p>
        <?php else: ?>
            <?php foreach ($sessoes as $sessao): ?>
                <?php
                $sid = (int)($sessao['id'] ?? 0);
                $ativa = $sid === $sessaoAtivaId;
                ?>
                <a href="<?= htmlspecialchars((string)$chat_base_url) ?>?sessao=<?= $sid ?>"
                   class="block px-2 py-2 rounded-lg text-sm truncate <?= $ativa ? 'bg-indigo-50 text-indigo-800 font-medium' : 'text-gray-700 hover:bg-gray-50' ?>"
                   title="<?= htmlspecialchars((string)($sessao['titulo'] ?? 'Conversa')) ?>">
                    <?= htmlspecialchars((string)($sessao['titulo'] ?? 'Conversa')) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>
