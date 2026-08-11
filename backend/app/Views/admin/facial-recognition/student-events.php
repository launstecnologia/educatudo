<?php
$page_header_title    = 'Entrada e Saída — ' . htmlspecialchars($student['nome'] ?? '');
$page_header_subtitle = htmlspecialchars(($student['turma_nome'] ?? '') ?: '');
ob_start(); ?>
<a href="<?= URL ?>/admin/students/<?= (int)($student['id'] ?? 0) ?>" class="btn-secondary text-sm">
    ← Voltar ao aluno
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<?php if (!$schema_ready): ?>
<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4 text-sm">
    Migration <code>066_facial_attendance.sql</code> ainda não foi executada nesta escola.
</div>
<?php elseif (empty($events)): ?>
<div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-gray-400">
    <i class="fa-solid fa-camera-slash text-3xl mb-3 block"></i>
    Nenhum registro de entrada ou saída encontrado para este aluno.
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Data/Hora</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Tipo</th>
                <th class="px-4 py-3 text-left text-gray-500 font-medium">Confiança</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($events as $ev): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-700">
                    <?= date('d/m/Y H:i', strtotime($ev['event_at'])) ?>
                </td>
                <td class="px-4 py-3">
                    <?php if (($ev['kind'] ?? '') === 'entry'): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Entrada
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Saída
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-gray-600">
                    <?php if (!empty($ev['confidence'])): ?>
                        <?= number_format((float)$ev['confidence'] * 100, 1) ?>%
                    <?php else: ?>
                        <span class="text-gray-400">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
