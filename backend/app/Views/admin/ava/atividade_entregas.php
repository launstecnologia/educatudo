<?php
$atividade = $atividade ?? [];
$entregas = $entregas ?? [];
$totalAlunos = (int) ($total_alunos ?? 0);
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$atividadeId = (int) ($atividade['id'] ?? 0);
$disciplinaId = (int) ($atividade['disciplina_id'] ?? 0);
$notaMax = (float) ($atividade['nota_maxima'] ?? 10);

$statusBadge = static function (string $s): array {
    return [
        'avaliada' => ['Avaliada', 'bg-green-100 text-green-700'],
        'enviada' => ['Enviada', 'bg-blue-100 text-blue-700'],
        'reenviar' => ['Reenviar', 'bg-amber-100 text-amber-700'],
        'rascunho' => ['Rascunho', 'bg-gray-100 text-gray-600'],
    ][$s] ?? [ucfirst($s), 'bg-gray-100 text-gray-600'];
};
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL . $base ?>/disciplinas/<?= $disciplinaId ?>/atividades" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Entregas</h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars((string) $atividade['titulo']) ?> · <?= count($entregas) ?> de <?= $totalAlunos ?> aluno(s)</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <?php if (empty($entregas)): ?>
        <div class="p-10 text-center text-gray-500"><i class="fa-solid fa-inbox text-3xl mb-3 text-gray-300"></i><p>Nenhuma entrega recebida ainda.</p></div>
    <?php else: ?>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enviada em</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nota</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($entregas as $e): [$txt, $cls] = $statusBadge((string) $e['status']); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) ($e['aluno_nome'] ?? 'Aluno')) ?></div>
                        <div class="text-xs text-gray-500"><?= (int) ($e['total_arquivos'] ?? 0) ?> arquivo(s)<?= !empty($e['atrasada']) ? ' · <span class="text-amber-600">atrasada</span>' : '' ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($e['enviada_em']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['enviada_em']))) : '—' ?></td>
                    <td class="px-6 py-4 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= htmlspecialchars($txt) ?></span></td>
                    <td class="px-6 py-4 text-center text-sm font-semibold text-gray-900"><?= $e['nota'] !== null ? htmlspecialchars(number_format((float) $e['nota'], 2, ',', '')) . '/' . number_format($notaMax, 2, ',', '') : '—' ?></td>
                    <td class="px-6 py-4 text-right">
                        <a href="<?= URL . $base ?>/entregas/<?= (int) $e['id'] ?>/corrigir" class="inline-flex items-center px-3 py-1.5 bg-primary text-primary rounded-lg text-xs font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-pen-to-square mr-1"></i> Corrigir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
