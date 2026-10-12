<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Gestão de Turmas 🏫
            </h2>
            <p class="text-gray-600">
                Gerencie todas as turmas da escola
            </p>
        </div>
        <a href="<?= URL ?>/admin/classes/create" 
           class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Nova Turma
        </a>
    </div>
</div>

<!-- Classes Table -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Turmas</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($turmas)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <p>Nenhuma turma cadastrada</p>
                            <a href="<?= URL ?>/admin/classes/create" class="btn-primary-custom mt-4 inline-block px-4 py-2 rounded-lg text-sm transition-colors hover:opacity-90">
                                Cadastrar Primeira Turma
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($turmas as $turma): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($turma['nome']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($turma['serie']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= $turma['total_alunos'] ?? 0 ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $turma['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $turma['ativo'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="<?= URL ?>/admin/classes/<?= $turma['id'] ?>/edit" 
                                   class="text-blue-600 hover:text-blue-900">Editar</a>
                                <button onclick="toggleStatusClass(<?= $turma['id'] ?>, <?= $turma['ativo'] ? 'false' : 'true' ?>)" 
                                        class="text-orange-600 hover:text-orange-900">
                                    <?= $turma['ativo'] ? 'Desativar' : 'Ativar' ?>
                                </button>
                                <button onclick="deleteClass(<?= $turma['id'] ?>)" 
                                        class="text-red-600 hover:text-red-900">Excluir</button>
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
    function toggleStatusClass(id, status) {
        if (confirm('Tem certeza que deseja alterar o status desta turma?')) {
            fetch(`<?= URL ?>/admin/classes/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ativo: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao alterar status: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erro de conexão');
            });
        }
    }

    function deleteClass(id) {
        if (confirm('Tem certeza que deseja excluir esta turma? Esta ação não pode ser desfeita.')) {
            fetch(`<?= URL ?>/admin/classes/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao excluir: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erro de conexão');
            });
        }
    }
</script>
