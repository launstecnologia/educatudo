<?php
/**
 * Lista de Blocos Modelo
 * Acesso: Coordenação
 */
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Blocos Modelo 📋
            </h2>
            <p class="text-gray-600">
                Templates reutilizáveis para criação rápida de Blocos de Prova
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/admin/provas" 
               class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                ← Voltar
            </a>
            <a href="<?= URL ?>/admin/blocos-modelo/criar" 
               class="bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-6 py-3 rounded-xl hover:from-blue-700 hover:to-cyan-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Novo Bloco Modelo
            </a>
        </div>
    </div>
</div>

<!-- Blocos Modelo Table -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Blocos Modelo</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professores</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criado Por</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($modelos)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1v-3z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg mb-2">Nenhum bloco modelo criado ainda</p>
                            <p class="text-sm text-gray-400 mb-4">Crie um template para agilizar a criação de blocos de prova</p>
                            <a href="<?= URL ?>/admin/blocos-modelo/criar" 
                               class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg hover:opacity-90">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Criar Primeiro Bloco Modelo
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($modelos as $modelo): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($modelo['nome']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500">
                                    <?= $modelo['descricao'] ? htmlspecialchars(substr($modelo['descricao'], 0, 100)) : '<span class="text-gray-400">Sem descrição</span>' ?>
                                    <?= $modelo['descricao'] && strlen($modelo['descricao']) > 100 ? '...' : '' ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?= $modelo['total_professores'] ?? 0 ?> professor(es)
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($modelo['criado_por_nome'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="<?= URL ?>/admin/blocos-modelo/<?= $modelo['id'] ?>/editar" 
                                       class="text-indigo-600 hover:text-indigo-900" title="Editar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <button onclick="excluirModelo(<?= $modelo['id'] ?>)" 
                                            class="text-red-600 hover:text-red-900" 
                                            title="Excluir">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
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
function excluirModelo(id) {
    if (!confirm('Deseja excluir este bloco modelo? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/blocos-modelo/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bloco modelo excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao excluir bloco modelo'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}
</script>

