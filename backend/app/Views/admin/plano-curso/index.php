<?php
$planos = $planos ?? [];
ob_start();
?>
<a href="<?= URL ?>/admin/plano-curso/form"
   class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i> Novo Plano de Curso
</a>
<a href="<?= URL ?>/admin/bncc"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-list-check mr-2 text-gray-500"></i> BNCC
</a>
<?php
$page_header_actions = ob_get_clean();
$page_header_title = 'Planos de Curso';
$page_header_subtitle = 'Conteúdo previsto, carga horária e habilidades da BNCC por série e matéria.';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Matéria', 'Série', 'Ano', 'Carga horária', 'Habilidades', 'Status', ''] as $h): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($planos)): ?>
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-regular fa-clipboard text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum plano de curso cadastrado.</p>
                    </td></tr>
                <?php else: foreach ($planos as $p):
                    $total = (int) ($p['total_habilidades'] ?? 0);
                    $trab = (int) ($p['habilidades_trabalhadas'] ?? 0); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($p['materia_nome'] ?? '—')) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['serie_nome'] ?? '—')) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['ano_letivo'] ?? '—')) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-700"><?= (int) ($p['carga_horaria_prevista'] ?? 0) ?>h</td>
                        <td class="px-6 py-3 text-sm text-gray-700"><?= $trab ?>/<?= $total ?></td>
                        <td class="px-6 py-3">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ($p['status'] ?? '') === 'aprovado' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= ($p['status'] ?? '') === 'aprovado' ? 'Aprovado' : 'Rascunho' ?>
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="<?= URL ?>/admin/plano-curso/form?id=<?= (int) $p['id'] ?>" class="text-sm font-medium text-green-700 hover:text-green-900">Abrir</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
