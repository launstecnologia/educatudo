<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">EducaLabs 🚀</h1>
            <p class="text-gray-600 mt-2">Crie apps com IA usando HTML, CSS e JavaScript.</p>
        </div>
        <a href="<?= URL ?>/educalabs/novo" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
            Novo Projeto
        </a>
    </div>
</div>

<?php if (empty($projects)): ?>
    <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
        <h3 class="text-xl font-semibold text-gray-900">Nenhum projeto criado</h3>
        <p class="text-gray-600 mt-2">Comece criando seu primeiro app com IA.</p>
        <a href="<?= URL ?>/educalabs/novo" class="mt-6 inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
            Criar primeiro projeto
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($projects as $project): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 flex flex-col">
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 mb-1"><?= htmlspecialchars($project['name'] ?? '') ?></h3>
                    <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($project['description'] ?? 'Sem descrição') ?></p>
                    <p class="text-xs text-gray-400">Atualizado em <?= date('d/m/Y H:i', strtotime($project['updated_at'] ?? 'now')) ?></p>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <a href="<?= URL ?>/educalabs/projetos/<?= htmlspecialchars($project['id']) ?>"
                       class="flex-1 text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold">
                        Abrir
                    </a>
                    <button type="button"
                            data-share-url="<?= URL ?>/educalabs/public/<?= htmlspecialchars($project['share_id'] ?? '') ?>"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-semibold"
                            onclick="copyShareLink(this)">
                        Compartilhar
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function copyShareLink(button) {
    const url = button.getAttribute('data-share-url');
    if (!url) {
        return;
    }

    navigator.clipboard.writeText(url).then(() => {
        const original = button.textContent;
        button.textContent = 'Copiado!';
        setTimeout(() => {
            button.textContent = original;
        }, 1500);
    });
}
</script>

