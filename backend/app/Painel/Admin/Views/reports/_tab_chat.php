<?php
$reports_filter_tab = 'chat';
$reports_filter_jornadas_extended = false;
require __DIR__ . '/_reports_filters_form.php';
?>
<!-- Estatísticas de Chat -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-blue-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Total de Conversas</p>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($chat_stats['total_conversas']) ?></p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-green-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Total de Mensagens</p>
                <p class="text-3xl font-bold text-green-600"><?= number_format($chat_stats['total_mensagens']) ?></p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h3l-4 4z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-purple-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Interações (Aluno)</p>
                <p class="text-3xl font-bold text-purple-600"><?= number_format($chat_stats['total_interacoes']) ?></p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Interações por Turma -->
<?php if (!empty($chat_stats['interacoes_por_turma'])): ?>
<div class="mt-6">
    <h4 class="text-md font-semibold text-gray-900 mb-4">Interações por Turma</h4>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mensagens</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($chat_stats['interacoes_por_turma'] as $row): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['turma_nome'] ?? 'Sem turma') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($row['total_conversas']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($row['total_mensagens']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($row['interacoes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lista de Alunos com Chat -->
<?php if (!empty($alunos_chat)): ?>
<div class="mt-6">
    <h4 class="text-md font-semibold text-gray-900 mb-4">Alunos com Interações no Chat</h4>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conversas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensagens</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($alunos_chat as $aluno): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-medium"><?= strtoupper(substr($aluno['nome'], 0, 1)) ?></span>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></div>
                                <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($aluno['ra']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($aluno['turma_nome'] ?? 'Sem turma') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= number_format($aluno['total_conversas']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= number_format($aluno['total_mensagens']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

