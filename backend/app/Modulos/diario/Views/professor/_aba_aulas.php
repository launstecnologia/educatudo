<?php
require_once __DIR__ . '/../../Services/ClassDiaryService.php';

use App\Modulos\Diario\Services\ClassDiaryService;
$labels = [
    'futura' => 'Futura',
    'pendente' => 'Pendente',
    'rascunho' => 'Rascunho',
    'finalizada' => 'Finalizada',
    'nao_realizada' => 'Não realizada',
];
$classes = [
    'futura' => 'bg-gray-100 text-gray-600',
    'pendente' => 'bg-amber-100 text-amber-800',
    'rascunho' => 'bg-blue-100 text-blue-800',
    'finalizada' => 'bg-green-100 text-green-800',
    'nao_realizada' => 'bg-red-100 text-red-800',
];
?>
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Data', 'Horário', 'Tipo', 'Conteúdo', 'Situação', 'Ação'] as $header): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($aulas)): ?>
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">Nenhuma aula prevista neste período.</td></tr>
                <?php else: ?>
                    <?php foreach ($aulas as $a):
                        $sit = (string) $a['situacao'];
                        $conteudo = trim(strip_tags((string) ($a['conteudo_realizado'] ?? '')));
                        $podeAbrir = in_array($sit, ['pendente', 'rascunho', 'finalizada', 'nao_realizada'], true);
                        $url = URL . '/professor/diario/abrir?grade_id=' . (int) $a['grade_horaria_id'] . '&data=' . urlencode((string) $a['data_aula']) . '&origem=diario';
                        $tipo = (string) ($a['tipo_aula'] ?? 'regular');
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= date('d/m/Y', strtotime((string) $a['data_aula'])) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars(substr((string) $a['horario_de'], 0, 5)) ?>–<?= htmlspecialchars(substr((string) $a['horario_ate'], 0, 5)) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars(ClassDiaryService::tipoAulaLabel($tipo)) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= $conteudo !== '' ? htmlspecialchars(mb_strimwidth($conteudo, 0, 60, '…')) : '<span class="text-gray-300">—</span>' ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $classes[$sit] ?? 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($labels[$sit] ?? $sit) ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($podeAbrir): ?>
                                    <a href="<?= htmlspecialchars($url) ?>" class="text-xs font-semibold text-purple-700 hover:underline">
                                        <?= $sit === 'pendente' ? 'Lançar' : 'Ver' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
