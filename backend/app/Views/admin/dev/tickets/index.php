<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Tickets de Suporte</h2>
            <p class="text-gray-600">Gerencie os tickets enviados pelos alunos.</p>
        </div>
        <div class="text-sm text-gray-600 bg-white rounded-lg border border-gray-200 px-4 py-2">
            Em aberto: <span class="font-semibold text-red-600"><?= (int) $open_count ?></span>
        </div>
    </div>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Tickets</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assunto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Mensagem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            Nenhum ticket encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">#<?= (int) $ticket['id'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-medium"><?= htmlspecialchars($ticket['aluno_nome'] ?? '') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($ticket['nickname'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($ticket['assunto'] ?? '') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars(ucfirst($ticket['categoria'] ?? 'geral')) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ($ticket['status'] ?? '') === 'fechado' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= ($ticket['status'] ?? '') === 'fechado' ? 'Fechado' : 'Aberto' ?>
                                </span>
                                <?php if (!empty($ticket['mensagens_nao_lidas'])): ?>
                                    <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                                        <?= (int) $ticket['mensagens_nao_lidas'] ?> nova(s)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($ticket['ultima_mensagem'])): ?>
                                    <?= date('d/m/Y H:i', strtotime($ticket['ultima_mensagem'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="<?= URL ?>/admin/dev/tickets/<?= (int) $ticket['id'] ?>"
                                   class="px-3 py-1 rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition-colors text-sm">
                                    Ver ticket
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

