<?php
$relatorios = is_array($relatorios ?? null) ? $relatorios : [];
$provas = is_array($provas_realizadas ?? null) ? $provas_realizadas : [];
$provasComQuestoes = array_values(array_filter($provas, function ($p) {
    return (int) ($p['questoes'] ?? 0) > 0;
}));
$jornadas = is_array($jornadas_detalhadas ?? null) ? $jornadas_detalhadas : [];
$kpiProvas = is_array($kpis_provas ?? null) ? $kpis_provas : [];
$kpiJornadas = is_array($kpis_jornadas ?? null) ? $kpis_jornadas : [];
?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
        <p class="text-gray-600">KPIs completos de jornadas e provas.</p>
    </div>

    <section>
        <h3 class="text-lg font-semibold text-gray-900 mb-3">KPI de Provas</h3>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
            <div class="rounded-lg border p-3"><p class="text-xs text-gray-500">Realizadas</p><p class="text-xl font-bold"><?= (int) ($kpiProvas['total_realizadas'] ?? 0) ?></p></div>
            <div class="rounded-lg border p-3"><p class="text-xs text-gray-500">Com questões</p><p class="text-xl font-bold"><?= (int) ($kpiProvas['total_com_questoes'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-xs text-green-700">Acertos</p><p class="text-xl font-bold text-green-800"><?= (int) ($kpiProvas['acertos'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-xs text-red-700">Erros</p><p class="text-xl font-bold text-red-800"><?= (int) ($kpiProvas['erros'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-gray-200 p-3"><p class="text-xs text-gray-500">Questões</p><p class="text-xl font-bold"><?= (int) ($kpiProvas['questoes'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-xs text-blue-700">Taxa acerto</p><p class="text-xl font-bold text-blue-800"><?= (int) ($kpiProvas['taxa_acerto_geral'] ?? 0) ?>%</p></div>
        </div>
        <?php if ((int) ($kpiProvas['total_sem_questoes'] ?? 0) > 0): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <?= (int) ($kpiProvas['total_sem_questoes'] ?? 0) ?> avaliação(ões) são de lançamento de nota sem questões; por isso não geram acertos/erros.
            </div>
        <?php endif; ?>
        <?php if (!empty($provasComQuestoes)): ?>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left">Prova</th>
                            <th class="px-3 py-2 text-left">Matéria</th>
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-center">Acertos</th>
                            <th class="px-3 py-2 text-center">Erros</th>
                            <th class="px-3 py-2 text-center">Questões</th>
                            <th class="px-3 py-2 text-center">Taxa</th>
                            <th class="px-3 py-2 text-center">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($provasComQuestoes as $prova): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-900"><?= htmlspecialchars((string) ($prova['prova_titulo'] ?? 'Prova'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars((string) ($prova['prova_materia'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-center"><?= htmlspecialchars((string) ($prova['status_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="px-3 py-2 text-center text-green-700 font-semibold"><?= (int) ($prova['acertos'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center text-red-700 font-semibold"><?= (int) ($prova['erros'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center"><?= (int) ($prova['questoes'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center"><?= (int) ($prova['taxa_acerto'] ?? 0) ?>%</td>
                                <td class="px-3 py-2 text-center text-gray-600"><?= !empty($prova['iniciado_em']) ? date('d/m/Y H:i', strtotime((string) $prova['iniciado_em'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">Nenhuma prova com questões encontrada para exibir acertos/erros.</p>
        <?php endif; ?>
    </section>

    <section>
        <h3 class="text-lg font-semibold text-gray-900 mb-3">KPI de Jornadas</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
            <div class="rounded-lg border p-3"><p class="text-xs text-gray-500">Total</p><p class="text-xl font-bold"><?= (int) ($kpiJornadas['total'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-xs text-green-700">Fez</p><p class="text-xl font-bold text-green-800"><?= (int) ($kpiJornadas['feitas'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-xs text-red-700">Não fez</p><p class="text-xl font-bold text-red-800"><?= (int) ($kpiJornadas['nao_feitas'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-xs text-blue-700">Concluídas</p><p class="text-xl font-bold text-blue-800"><?= (int) ($kpiJornadas['concluidas'] ?? 0) ?></p></div>
            <div class="rounded-lg border border-purple-200 bg-purple-50 p-3"><p class="text-xs text-purple-700">Taxa conclusão</p><p class="text-xl font-bold text-purple-800"><?= (int) ($kpiJornadas['taxa_conclusao'] ?? 0) ?>%</p></div>
        </div>
    </section>

    <section>
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Relatórios salvos</h3>
        <?php if (empty($relatorios)): ?>
            <p class="text-gray-500">Nenhum relatório disponível</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($relatorios as $relatorio): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($relatorio['titulo'] ?? 'Relatório') ?></h4>
                        <p class="text-sm text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($relatorio['created_at'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
