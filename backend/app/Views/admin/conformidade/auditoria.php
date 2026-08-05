<?php
$painel = $painel ?? [];
$escola = $painel['escola'] ?? [];
$indicadores = $painel['indicadores'] ?? [];
$geral = $painel['conformidade_geral'] ?? null;
$badge = [
    'verde' => 'bg-green-100 text-green-800',
    'amarelo' => 'bg-amber-100 text-amber-800',
    'vermelho' => 'bg-red-100 text-red-800',
    'indisponivel' => 'bg-gray-100 text-gray-500',
];
$page_header_title = 'Modo Auditoria';
$page_header_subtitle = 'Visão consolidada e somente leitura para Secretaria de Educação e auditorias.';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 mb-6">
    <i class="fa-solid fa-circle-info mr-2"></i> Modo somente leitura. Os dados abaixo são consolidados automaticamente a partir dos módulos do sistema.
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden divide-y divide-gray-200 mb-6">
    <section class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Dados da escola</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div><p class="text-xs text-gray-500 uppercase">Ano letivo</p><p class="text-xl font-bold text-gray-900"><?= (int) ($escola['ano_letivo'] ?? date('Y')) ?></p></div>
            <div><p class="text-xs text-gray-500 uppercase">Turmas</p><p class="text-xl font-bold text-gray-900"><?= (int) ($escola['turmas'] ?? 0) ?></p></div>
            <div><p class="text-xs text-gray-500 uppercase">Alunos ativos</p><p class="text-xl font-bold text-gray-900"><?= (int) ($escola['alunos'] ?? 0) ?></p></div>
            <div><p class="text-xs text-gray-500 uppercase">Professores</p><p class="text-xl font-bold text-gray-900"><?= (int) ($escola['professores'] ?? 0) ?></p></div>
        </div>
    </section>
    <section class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Indicadores de conformidade</h3>
            <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?= $badge[$painel['conformidade_cor'] ?? 'indisponivel'] ?>">
                Geral: <?= $geral !== null ? number_format((float) $geral, 1, ',', '') . '%' : '—' ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Indicador</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalhe</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($indicadores as $ind): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($ind['label'] ?? '')) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $badge[$ind['cor'] ?? 'indisponivel'] ?>">
                                    <?= $ind['percentual'] !== null ? number_format((float) $ind['percentual'], 1, ',', '') . '%' : 'Indisponível' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars((string) ($ind['detalhe'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
