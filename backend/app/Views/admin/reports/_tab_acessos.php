<?php
$reports_filter_tab = 'acessos';
$reports_filter_jornadas_extended = false;
require __DIR__ . '/_reports_filters_form.php';
?>
<!-- Lista de Sessões de Acesso -->
<?php if (!empty($sessoes_acesso)): ?>
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Login</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logout</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tempo de Uso</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($sessoes_acesso as $sessao): ?>
            <tr class="<?= $sessao['status'] === 'ativo' ? 'bg-green-50' : '' ?>">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($sessao['aluno_nome']) ?></div>
                    <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($sessao['ra']) ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= htmlspecialchars($sessao['turma_nome'] ?? 'Sem turma') ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <?= date('d/m/Y H:i:s', strtotime($sessao['login_at'])) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= $sessao['logout_at'] ? date('d/m/Y H:i:s', strtotime($sessao['logout_at'])) : '<span class="text-green-600 font-medium">Online</span>' ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono <?= $sessao['status'] === 'ativo' ? 'text-green-700 font-bold' : 'text-gray-700' ?>">
                    <?= htmlspecialchars($sessao['tempo_formatado']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($sessao['status'] === 'ativo'): ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                            🟢 Online
                        </span>
                    <?php elseif ($sessao['status'] === 'finalizado'): ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                            Finalizado
                        </span>
                    <?php else: ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            Expirado
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= htmlspecialchars($sessao['ip_address'] ?? 'N/A') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="text-center py-12">
    <p class="text-gray-500">Nenhuma sessão de acesso encontrada para os filtros selecionados.</p>
</div>
<?php endif; ?>

