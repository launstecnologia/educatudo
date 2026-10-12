<?php
/**
 * Resultado do aluno no bloco: lista de provas com link para ver resultado de cada uma
 */
$realizacoes = $realizacoes ?? [];
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Resultado no bloco: <?= htmlspecialchars($aluno['nome']) ?>
            </h2>
            <p class="text-gray-600">
                Bloco: <?= htmlspecialchars($bloco['titulo']) ?> • RA: <?= htmlspecialchars($aluno['ra'] ?? '') ?>
            </p>
        </div>
        <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/resultados"
           class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
            ← Voltar aos resultados
        </a>
    </div>
</div>

<?php if (!empty($flash_message)): ?>
<div class="mb-6 p-4 rounded-lg border-2 <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : (($flash_type ?? '') === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>">
    <p class="font-medium"><?= htmlspecialchars($flash_message) ?></p>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Provas do bloco</h3>
    <p class="text-sm text-gray-600 mb-6">Clique em "Ver resultado" para ver as respostas do aluno em cada prova.</p>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prova / Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($provas as $prova):
                    $real = $realizacoes[$prova['id']] ?? null;
                    $status = isset($real['status']) ? (string)$real['status'] : 'não realizada';
                    $statusLower = strtolower($status);
                    $nota = $real['nota'] ?? '—';
                    $ehCancelada = ($statusLower === 'cancelada');
                    $ehFinalizado = ($statusLower === 'finalizado');
                ?>
                    <tr>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['titulo']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($prova['materia_nome'] ?? '') ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded <?= $ehFinalizado ? 'bg-green-100 text-green-800' : ($ehCancelada ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') ?>">
                                <?= $ehFinalizado ? 'Finalizado' : ($ehCancelada ? 'Cancelada' : ucfirst(str_replace('_', ' ', $status))) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= $ehFinalizado && $nota !== '—' ? number_format((float)$nota, 1, ',', '.') : $nota ?></td>
                        <td class="px-6 py-4">
                            <?php if ($ehFinalizado): ?>
                                <a href="<?= URL ?>/admin/provas/resultado-aluno/<?= (int)$prova['id'] ?>/<?= (int)$aluno['id'] ?>"
                                   class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                    Ver resultado
                                </a>
                            <?php elseif ($ehCancelada): ?>
                                <form method="post" action="<?= URL ?>/admin/provas/liberar-tentativa/<?= (int)$prova['id'] ?>/<?= (int)$aluno['id'] ?>" class="inline" onsubmit="return confirm('Liberar nova tentativa para este aluno? Ele poderá realizar a prova novamente.');">
                                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded text-sm font-medium cursor-pointer">
                                        Liberar nova tentativa
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-500 text-sm">Para liberar novamente, edite o bloco e estenda o horário final da prova.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
