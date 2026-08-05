<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Critérios — <?= htmlspecialchars($textType['name']) ?>
            </h2>
            <p class="text-gray-600">
                <a href="<?= URL ?>/admin/redacao-configuravel" class="text-purple-600 hover:underline">Redação Configurável</a>
                &rarr; <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos" class="text-purple-600 hover:underline"><?= htmlspecialchars($board['name']) ?></a>
                &rarr; <?= htmlspecialchars($textType['name']) ?>
            </p>
        </div>
        <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/criterios/novo"
           class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Novo Critério
        </a>
    </div>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Critérios de correção</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Identificador</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pontuação máx.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            Nenhum critério. <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/criterios/novo" class="text-purple-600 hover:underline">Cadastrar primeiro</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= (int)$item['order_position'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['slug']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format((float)$item['max_score'], 1, ',', '.') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/criterios/<?= (int)$item['id'] ?>/editar" class="text-blue-600 hover:text-blue-900">Editar</a>
                            <button type="button" onclick="deleteCriterion(<?= (int)$board['id'] ?>, <?= (int)$textType['id'] ?>, <?= (int)$item['id'] ?>)" class="text-red-600 hover:text-red-900">Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deleteCriterion(boardId, textTypeId, id) {
    if (!confirm('Excluir este critério?')) return;
    const fd = new FormData(); fd.append('_token', document.querySelector('input[name="_token"]')?.value || ''); fd.append('_method', 'DELETE');
    fetch('<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + textTypeId + '/criterios/' + id, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || 'Erro'); }).catch(() => alert('Erro de conexão'));
}
</script>
