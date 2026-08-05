<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Prompts de Correção — <?= htmlspecialchars($textType['name']) ?>
            </h2>
            <p class="text-gray-600">
                <a href="<?= URL ?>/admin/redacao-configuravel" class="text-purple-600 hover:underline">Redação Configurável</a>
                &rarr; <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos" class="text-purple-600 hover:underline"><?= htmlspecialchars($board['name']) ?></a>
                &rarr; <?= htmlspecialchars($textType['name']) ?>
            </p>
        </div>
        <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/prompts/novo"
           class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Novo Prompt
        </a>
    </div>
</div>

<?php if ($activePrompt): ?>
<div class="mb-4 p-4 rounded-lg border bg-green-50 border-green-200 text-green-800">
    <strong>Prompt ativo:</strong> Versão <?= (int)$activePrompt['version'] ?> (ID <?= (int)$activePrompt['id'] ?>)
</div>
<?php endif; ?>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Versões do prompt (apenas um ativo por banca + tipo)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atualizado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            Nenhum prompt. <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/prompts/novo" class="text-purple-600 hover:underline">Cadastrar primeiro</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">v<?= (int)$p['version'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $p['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $p['is_active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($p['updated_at'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/prompts/<?= (int)$p['id'] ?>/editar" class="text-blue-600 hover:text-blue-900">Editar</a>
                            <?php if (!$p['is_active']): ?>
                            <button type="button" onclick="setActive(<?= (int)$board['id'] ?>, <?= (int)$textType['id'] ?>, <?= (int)$p['id'] ?>)" class="text-green-600 hover:text-green-900">Definir como ativo</button>
                            <?php endif; ?>
                            <button type="button" onclick="deletePrompt(<?= (int)$board['id'] ?>, <?= (int)$textType['id'] ?>, <?= (int)$p['id'] ?>)" class="text-red-600 hover:text-red-900">Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function setActive(boardId, textTypeId, id) {
    if (!confirm('Definir este prompt como ativo? O prompt ativo atual será desativado.')) return;
    fetch('<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + textTypeId + '/prompts/' + id + '/ativar', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || 'Erro'); }).catch(() => alert('Erro de conexão'));
}
function deletePrompt(boardId, textTypeId, id) {
    if (!confirm('Excluir este prompt?')) return;
    const fd = new FormData(); fd.append('_token', document.querySelector('input[name="_token"]')?.value || ''); fd.append('_method', 'DELETE');
    fetch('<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + textTypeId + '/prompts/' + id, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || 'Erro'); }).catch(() => alert('Erro de conexão'));
}
</script>
