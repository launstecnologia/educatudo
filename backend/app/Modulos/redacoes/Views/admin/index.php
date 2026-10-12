<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Redação Configurável
            </h2>
            <p class="text-gray-600">
                Bancas, tipos textuais, critérios e prompts de correção por IA
            </p>
        </div>
        <a href="<?= URL ?>/admin/redacao-configuravel/bancas/novo"
           class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Nova Banca
        </a>
    </div>
</div>

<?php
$flash = $message ?? null;
if ($flash && is_array($flash) && !empty($flash['message'])):
    $type = $flash['type'] ?? 'info';
    $cls = $type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700';
?>
<div class="mb-4 p-4 rounded-lg border <?= $cls ?>">
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Boards Table -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Bancas</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banca</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Identificador</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($boards)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            Nenhuma banca cadastrada. <a href="<?= URL ?>/admin/redacao-configuravel/bancas/novo" class="text-purple-600 hover:underline">Cadastrar primeira banca</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($boards as $b): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($b['name']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($b['slug']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $b['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $b['is_active'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$b['id'] ?>/tipos" class="text-indigo-600 hover:text-indigo-900">Tipos</a>
                            <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$b['id'] ?>/editar" class="text-blue-600 hover:text-blue-900">Editar</a>
                            <button type="button" onclick="toggleBoard(<?= (int)$b['id'] ?>, <?= (int)$b['is_active'] ?>)" class="text-amber-600 hover:text-amber-900">
                                <?= $b['is_active'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                            <button type="button" onclick="deleteBoard(<?= (int)$b['id'] ?>)" class="text-red-600 hover:text-red-900">Excluir</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleBoard(id, current) {
    if (!confirm(current ? 'Desativar esta banca?' : 'Ativar esta banca?')) return;
    fetch('<?= URL ?>/admin/redacao-configuravel/bancas/' + id + '/toggle-status', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || 'Erro'); })
        .catch(() => alert('Erro de conexão'));
}
function deleteBoard(id) {
    if (!confirm('Excluir esta banca? Tipos, critérios e prompts vinculados também serão removidos.')) return;
    const fd = new FormData(); fd.append('_method', 'DELETE');
    fetch('<?= URL ?>/admin/redacao-configuravel/bancas/' + id, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || 'Erro'); })
        .catch(() => alert('Erro de conexão'));
}
</script>
