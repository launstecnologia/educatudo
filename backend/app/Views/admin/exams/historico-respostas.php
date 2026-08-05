<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
?>
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Histórico de alterações de respostas</h2>
            <p class="text-gray-600">Prova: <?= htmlspecialchars($prova['titulo']) ?></p>
            <p class="text-sm text-gray-500">Aluno: <strong><?= htmlspecialchars($aluno['nome']) ?></strong></p>
        </div>
        <a href="<?= URL ?>/admin/provas/resultado-aluno/<?= (int)$prova['id'] ?>/<?= (int)$aluno['id'] ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Voltar ao resultado</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <?php if (empty($logs)): ?>
        <div class="p-8 text-center text-gray-500">Nenhum registro de alteração encontrado.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Data/Hora</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Questão (id)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Alternativa / Resposta</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Ação</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">User-Agent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($logs as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                <?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= (int)$row['questao_id'] ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate" title="<?= htmlspecialchars($row['alternativa_texto'] ?? $row['resposta_texto'] ?? '-') ?>">
                                <?php if (!empty($row['alternativa_texto'])): ?>
                                    <?= htmlspecialchars(mb_substr(strip_tags($row['alternativa_texto']), 0, 80)) ?><?= mb_strlen(strip_tags($row['alternativa_texto'])) > 80 ? '…' : '' ?>
                                <?php elseif (!empty($row['resposta_texto'])): ?>
                                    Dissertativa: <?= htmlspecialchars(mb_substr($row['resposta_texto'], 0, 60)) ?><?= mb_strlen($row['resposta_texto']) > 60 ? '…' : '' ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $row['tipo_acao'] === 'marcou' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= $row['tipo_acao'] === 'marcou' ? 'Marcou' : 'Alterou' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 font-mono"><?= htmlspecialchars($row['ip'] ?? '') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($row['user_agent'] ?? '') ?>">
                                <?= htmlspecialchars(mb_substr($row['user_agent'] ?? '', 0, 50)) ?><?= mb_strlen($row['user_agent'] ?? '') > 50 ? '…' : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
