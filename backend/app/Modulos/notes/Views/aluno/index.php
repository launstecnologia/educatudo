<?php
$pastas = $pastas ?? [];
$porPasta = $porPasta ?? [];
$semPasta = $semPasta ?? [];
?>
<div class="container mx-auto px-4 py-6 max-w-7xl w-full">
    <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-1">📓 Meu Caderno</h1>
            <p class="text-gray-600">Organize suas anotações em pastas de estudo</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button type="button" id="btn-nova-pasta" class="px-4 py-2 border border-amber-700/40 text-amber-800 bg-amber-50/80 rounded-lg hover:bg-amber-100 font-medium transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                Nova pasta
            </button>
            <a href="<?= URL ?>/caderno/novo" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Nova anotação
            </a>
            <a href="<?= URL ?>/caderno/novo-excalidraw" class="px-4 py-2 border border-violet-500 text-violet-700 rounded-lg hover:bg-violet-50 font-medium transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                Nova anotação (Excalidraw)
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg border border-green-300"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg border border-red-300"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Modal nova pasta -->
    <div id="modal-nova-pasta" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40" onclick="document.getElementById('modal-nova-pasta').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Nova pasta de estudo</h3>
                <form method="POST" action="<?= URL ?>/caderno/pasta" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <div>
                        <label for="nome_pasta" class="block text-sm font-medium text-gray-700 mb-1">Nome da pasta</label>
                        <input type="text" id="nome_pasta" name="nome_pasta" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Ex: Biologia - 1º bimestre">
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" onclick="document.getElementById('modal-nova-pasta').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">Criar pasta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="caderno-wrapper">
        <h2 class="caderno-page-title text-2xl">Anotações</h2>

        <?php if (empty($pastas) && empty($semPasta)): ?>
            <div class="text-center py-12">
                <p class="text-gray-600 mb-4">Você ainda não tem anotações nem pastas.</p>
                <p class="text-gray-500 text-sm mb-4">Crie uma pasta para organizar por matéria ou tema e depois adicione anotações.</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="<?= URL ?>/caderno/novo" class="inline-flex items-center gap-2 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Criar primeira anotação
                    </a>
                    <a href="<?= URL ?>/caderno/novo-excalidraw" class="inline-flex items-center gap-2 px-4 py-2 border border-violet-500 text-violet-700 rounded-lg hover:bg-violet-50 font-medium">
                        Nova anotação (Excalidraw)
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($pastas as $pasta): ?>
                <?php $itensPasta = $porPasta[$pasta['id']] ?? []; ?>
                <div class="caderno-folder">
                    <div class="caderno-folder-header">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                            <?= htmlspecialchars($pasta['nome']) ?>
                            <span class="text-sm font-normal text-gray-500">(<?= count($itensPasta) ?>)</span>
                        </span>
                        <div class="flex items-center gap-1">
                            <form method="POST" action="<?= URL ?>/caderno/pasta/<?= (int)$pasta['id'] ?>/excluir" class="inline" onsubmit="return confirm('Excluir esta pasta? As anotações continuarão no caderno.');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 rounded" title="Excluir pasta"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </form>
                        </div>
                    </div>
                    <div class="caderno-folder-body">
                        <?php if (empty($itensPasta)): ?>
                            <p class="text-sm text-gray-500 py-2">Nenhuma anotação nesta pasta.</p>
                        <?php else: ?>
                            <ul class="caderno-list">
                                <?php foreach ($itensPasta as $item): ?>
                                    <li>
                                        <a href="<?= URL ?>/caderno/<?= (int)$item['id'] ?>" class="flex items-center justify-between gap-2 hover:text-green-700 transition-colors">
                                            <span class="font-medium text-gray-900 truncate"><?= htmlspecialchars($item['titulo']) ?></span>
                                            <span class="text-xs text-gray-500 flex-shrink-0"><?= date('d/m/Y', strtotime($item['updated_at'] ?? $item['created_at'])) ?></span>
                                        </a>
                                        <?php if (!empty($item['materia_nome'])): ?>
                                            <span class="text-xs text-green-700"><?= htmlspecialchars($item['materia_nome']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($semPasta)): ?>
                <div class="caderno-folder mt-4">
                    <div class="caderno-folder-header">
                        <span class="flex items-center gap-2 text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293L17 11.586A1 1 0 0117.586 13H19a2 2 0 012 2v7a2 2 0 01-2 2z"></path></svg>
                            Sem pasta
                        </span>
                    </div>
                    <div class="caderno-folder-body">
                        <ul class="caderno-list">
                            <?php foreach ($semPasta as $item): ?>
                                <li>
                                    <a href="<?= URL ?>/caderno/<?= (int)$item['id'] ?>" class="flex items-center justify-between gap-2 hover:text-green-700 transition-colors">
                                        <span class="font-medium text-gray-900 truncate"><?= htmlspecialchars($item['titulo']) ?></span>
                                        <span class="text-xs text-gray-500 flex-shrink-0"><?= date('d/m/Y', strtotime($item['updated_at'] ?? $item['created_at'])) ?></span>
                                    </a>
                                    <?php if (!empty($item['materia_nome'])): ?>
                                        <span class="text-xs text-green-700"><?= htmlspecialchars($item['materia_nome']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('btn-nova-pasta')?.addEventListener('click', function() {
    document.getElementById('modal-nova-pasta').classList.remove('hidden');
});
</script>
