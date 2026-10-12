<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Histórico de redações</h2>
    <p class="text-gray-600">Todas as suas redações enviadas.</p>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proposta</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banca / Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhuma redação enviada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($submissions as $s): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($s['proposal_title']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($s['board_name']) ?> — <?= htmlspecialchars($s['text_type_name']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $s['status'] === 'submitted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $s['status'] === 'submitted' ? 'Enviado' : 'Rascunho' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($s['updated_at'])) ?></td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <?php if ($s['status'] === 'submitted'): ?>
                            <a href="<?= URL ?>/jornada-redacao/correcao/<?= (int)$s['id'] ?>" class="text-indigo-600 hover:text-indigo-900">Ver correção</a>
                            <?php else: ?>
                            <a href="<?= URL ?>/jornada-redacao/<?= (int)$s['proposal_id'] ?>/escrever" class="text-blue-600 hover:text-blue-900">Continuar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="mt-6">
    <a href="<?= URL ?>/jornada-redacao" class="text-purple-600 hover:underline">Voltar às redações disponíveis</a>
</p>
