<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Tipos de Avaliação</h2>
            <p class="text-gray-600">Cadastre os tipos usados nos eventos de Provas Online.</p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/provas" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">← Voltar</a>
            <a href="<?= URL ?>/admin/provas/tipos-avaliacao/criar" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">Novo Tipo</a>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Tipos</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ordem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($tipos)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum tipo de avaliação cadastrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tipos as $tipo): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($tipo['nome']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($tipo['descricao']) ? htmlspecialchars($tipo['descricao']) : '<span class="text-gray-400">Sem descrição</span>' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= (int)($tipo['ordem'] ?? 0) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($tipo['ativo'])): ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-3">
                                    <a href="<?= URL ?>/admin/provas/tipos-avaliacao/<?= (int)$tipo['id'] ?>/editar" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    <button type="button" onclick="excluirTipo(<?= (int)$tipo['id'] ?>, '<?= htmlspecialchars($tipo['nome'], ENT_QUOTES) ?>')" class="text-red-600 hover:text-red-900">Excluir</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function excluirTipo(id, nome) {
    if (!confirm(`Deseja excluir o tipo "${nome}"?`)) return;
    fetch(`<?= URL ?>/admin/provas/tipos-avaliacao/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
                return;
            }
            alert(data.error || 'Erro ao excluir tipo de avaliação.');
        })
        .catch(() => alert('Erro ao excluir tipo de avaliação.'));
}
</script>

