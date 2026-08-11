<div class="max-w-6xl mx-auto space-y-6">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900">Meu Material</h2>
            <p class="text-sm text-gray-600">Envie apostilas em PDF para que a IA extraia conteúdo, gere exercícios e converse com professores e alunos sobre o material.</p>
        </div>
        <a href="<?= URL ?>/admin/apostilas-ia/criar" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors whitespace-nowrap hover:opacity-90">
            Novo material
        </a>
    </div>

    <?php if (empty($items)): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-6 text-gray-500">
            Nenhuma apostila enviada ainda.
        </div>
    <?php else: ?>
        <?php
        $badgeClasses = [
            'pendente'    => 'bg-gray-100 text-gray-700',
            'processando' => 'bg-blue-100 text-blue-700',
            'pronto'      => 'bg-green-100 text-green-700',
            'erro'        => 'bg-red-100 text-red-700',
        ];
        $badgeLabels = [
            'pendente'    => 'Pendente',
            'processando' => 'Processando',
            'pronto'      => 'Pronto',
            'erro'        => 'Erro',
        ];
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <?php foreach ($items as $item): ?>
                <?php
                $status      = (string)($item['status'] ?? 'pendente');
                $itemId      = (int)$item['id'];
                $badgeClass  = $badgeClasses[$status] ?? 'bg-gray-100 text-gray-700';
                $badgeLabel  = $badgeLabels[$status] ?? ucfirst($status);
                $capaUrl     = URL . '/admin/apostilas-ia/' . $itemId . '/capa';
                $pdfUrl      = URL . '/admin/apostilas-ia/' . $itemId . '/pdf';
                $editarUrl   = URL . '/admin/apostilas-ia/' . $itemId . '/editar';
                ?>
                <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden flex flex-col" data-apostila-id="<?= $itemId ?>">
                    <!-- Capa -->
                    <div class="relative bg-gray-100 aspect-[3/4] flex items-center justify-center">
                        <img src="<?= htmlspecialchars($capaUrl) ?>"
                             alt="Capa de <?= htmlspecialchars((string)$item['titulo']) ?>"
                             class="w-full h-full object-cover" loading="lazy"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="hidden w-full h-full items-center justify-center text-gray-400 text-5xl absolute inset-0">📘</div>
                        <span class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-medium status-badge <?= $badgeClass ?>"
                              <?= $status === 'erro' && !empty($item['erro']) ? 'title="' . htmlspecialchars((string)$item['erro']) . '"' : '' ?>>
                            <?php if ($status === 'processando'): ?>
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-500 animate-pulse mr-1"></span>
                            <?php endif; ?>
                            <span class="status-label"><?= htmlspecialchars($badgeLabel) ?></span>
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-3 flex flex-col flex-1">
                        <h3 class="font-semibold text-gray-900 text-xs leading-snug line-clamp-2">
                            <?= htmlspecialchars((string)$item['titulo']) ?>
                        </h3>
                        <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                            <?= !empty($item['disciplina_nome']) ? htmlspecialchars($item['disciplina_nome']) : '—' ?>
                            <?php if (!empty($item['turmas_nomes'])): ?>
                                · <?= htmlspecialchars(implode(', ', $item['turmas_nomes'])) ?>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5 status-total-paginas">
                            <?= (int)($item['total_paginas'] ?? 0) ?> pág.
                        </p>

                        <!-- Ações -->
                        <div class="mt-2 flex flex-col gap-1.5">
                            <?php if (!empty($item['pdf_disponivel'])): ?>
                                <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener noreferrer"
                                   class="inline-block w-full text-center bg-gray-100 text-gray-800 px-2 py-1.5 rounded-lg hover:bg-gray-200 text-xs font-medium">
                                    📖 Ver Apostila
                                </a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($editarUrl) ?>"
                               class="inline-block w-full text-center bg-indigo-50 text-indigo-700 px-2 py-1.5 rounded-lg hover:bg-indigo-100 text-xs font-medium">
                                ✏️ Editar
                            </a>
                            <?php if (in_array($status, ['erro', 'pronto'], true) && empty($item['is_legado'])): ?>
                                <form action="<?= URL ?>/admin/apostilas-ia/<?= $itemId ?>/reprocessar" method="POST"
                                      onsubmit="return confirm('Reprocessar esta apostila?');" class="w-full">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <button type="submit"
                                            class="w-full text-center bg-orange-50 text-orange-700 px-2 py-1.5 rounded-lg hover:bg-orange-100 text-xs font-medium">
                                        🔄 Reprocessar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var cards = document.querySelectorAll('[data-apostila-id]');
    var pending = [];
    cards.forEach(function (card) {
        var label = card.querySelector('.status-label');
        if (label && (label.textContent.trim() === 'Processando' || label.textContent.trim() === 'Pendente')) {
            pending.push(card.getAttribute('data-apostila-id'));
        }
    });

    if (pending.length === 0) return;

    var badgeClasses = {
        pendente: 'bg-gray-100 text-gray-700',
        processando: 'bg-blue-100 text-blue-700',
        pronto: 'bg-green-100 text-green-700',
        erro: 'bg-red-100 text-red-700'
    };
    var badgeLabels = { pendente: 'Pendente', processando: 'Processando', pronto: 'Pronto', erro: 'Erro' };

    function poll() {
        pending.forEach(function (id) {
            fetch('<?= URL ?>/admin/apostilas-ia/' + id + '/status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (!data || !data.status) return;
                    var card = document.querySelector('[data-apostila-id="' + id + '"]');
                    if (!card) return;
                    var badge = card.querySelector('.status-badge');
                    var label = card.querySelector('.status-label');
                    var pag   = card.querySelector('.status-total-paginas');
                    badge.className = 'absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-medium status-badge ' + (badgeClasses[data.status] || 'bg-gray-100 text-gray-700');
                    label.textContent = badgeLabels[data.status] || data.status;
                    if (pag) pag.textContent = (data.total_paginas || 0) + ' pág.';
                    if (data.status === 'pronto' || data.status === 'erro') {
                        pending = pending.filter(function (pid) { return pid !== id; });
                        location.reload();
                    }
                })
                .catch(function () {});
        });
        if (pending.length > 0) setTimeout(poll, 5000);
    }

    setTimeout(poll, 5000);
})();
</script>
