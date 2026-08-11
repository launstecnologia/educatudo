<?php
/**
 * Dev Settings - Todos os logs do sistema
 * Layout em duas colunas: à esquerda lista + excluir; à direita conteúdo do log.
 */
$logFiles = $log_files ?? [];
?>
<!-- logs-layout-v2: se você vê este comentário no código-fonte, a view nova está em uso -->
<?php
$selectedFile = $selected_file ?? null;
$content = $content ?? '';
$csrf_token = $csrf_token ?? '';
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? '';
$confirmDeleteJs = $selectedFile ? "return confirm('Excluir o log \\'" . addslashes(htmlspecialchars($selectedFile)) . "\\'?');" : 'return false;';
?>
<style>
    .logs-conteudo-pre {
        overflow-y: scroll !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #6b7280 #1f2937;
    }
    .logs-conteudo-pre::-webkit-scrollbar { width: 10px; }
    .logs-conteudo-pre::-webkit-scrollbar-track { background: #1f2937; border-radius: 0 8px 8px 0; }
    .logs-conteudo-pre::-webkit-scrollbar-thumb { background: #6b7280; border-radius: 5px; }
    .logs-conteudo-pre::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
</style>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- COLUNA ESQUERDA: lista de arquivos e ações (sempre visível) -->
    <div class="w-full lg:w-72 flex-shrink-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-base font-semibold text-gray-900">Logs do Sistema</h2>
            <a href="<?= URL ?>/admin/dev" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-800">← Voltar para Dev Settings</a>
        </div>
        <div class="p-4 border-b border-gray-100">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Arquivos</h3>
            <?php if (empty($logFiles)): ?>
                <p class="text-sm text-gray-500">Nenhum arquivo .log encontrado.</p>
            <?php else: ?>
                <ul class="space-y-1 max-h-[50vh] overflow-y-auto">
                    <?php foreach ($logFiles as $f): ?>
                    <li class="flex items-center gap-1 group">
                        <a href="<?= URL ?>/admin/dev-settings/logs?file=<?= urlencode($f['name']) ?>"
                           class="flex-1 min-w-0 truncate text-sm py-1.5 px-2 rounded <?= ($selectedFile === $f['name']) ? 'bg-indigo-600 text-white font-medium' : 'text-gray-700 hover:bg-gray-100' ?>"
                           title="<?= htmlspecialchars($f['name']) ?>">
                            <?= htmlspecialchars($f['name']) ?>
                        </a>
                        <form method="post" action="<?= URL ?>/admin/dev-settings/logs/delete" class="flex-shrink-0 inline" onsubmit="return confirm('Excluir \'<?= htmlspecialchars(addslashes($f['name'])) ?>\'?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="file" value="<?= htmlspecialchars($f['name']) ?>">
                            <button type="submit" class="p-1.5 rounded text-red-600 hover:bg-red-50" title="Excluir">🗑️</button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php if ($selectedFile): ?>
        <div class="p-4 border-t border-gray-200 bg-red-50">
            <p class="text-xs text-gray-600 mb-2">Arquivo selecionado:</p>
            <p class="text-sm font-mono text-gray-800 truncate mb-3" title="<?= htmlspecialchars($selectedFile) ?>"><?= htmlspecialchars($selectedFile) ?></p>
            <form method="post" action="<?= URL ?>/admin/dev-settings/logs/delete" onsubmit="<?= $confirmDeleteJs ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="file" value="<?= htmlspecialchars($selectedFile) ?>">
                <button type="submit" class="w-full py-2 px-3 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
                    🗑️ Excluir este log
                </button>
            </form>
        </div>
        <?php endif; ?>
        <details class="border-t border-gray-100">
            <summary class="p-3 text-xs font-medium text-gray-500 cursor-pointer hover:bg-gray-50">Tipos de log que aparecem</summary>
            <ul class="p-3 pt-0 text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                <li>app_*, jornadas_*, push_*, openai_*, database_*, auth_*, api_*, email_*, transcricao_*, correcao_redacao.log, etc.</li>
            </ul>
        </details>
    </div>

    <!-- COLUNA DIREITA: conteúdo do log -->
    <div class="flex-1 min-w-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
        <?php if ($flash_message !== ''): ?>
        <div class="p-4 <?= $flash_type === 'success' ? 'bg-green-50 text-green-800 border-b border-green-200' : 'bg-red-50 text-red-800 border-b border-red-200' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
        <?php endif; ?>
        <div class="p-4 flex-1 flex flex-col min-h-0">
            <?php if (!$selectedFile): ?>
                <p class="text-gray-500 py-8">Selecione um arquivo na lista à esquerda para visualizar ou use 🗑️ para excluir.</p>
            <?php else: ?>
                <div class="flex items-center justify-between gap-2 mb-3 flex-shrink-0">
                    <code class="text-sm font-mono bg-gray-100 px-2 py-1 rounded truncate"><?= htmlspecialchars($selectedFile) ?></code>
                </div>
                <pre class="logs-conteudo-pre flex-1 p-4 bg-gray-900 text-gray-100 rounded-lg text-xs leading-relaxed min-h-[60vh] whitespace-pre-wrap break-words"><?= htmlspecialchars($content) ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>
