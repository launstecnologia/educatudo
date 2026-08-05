<?php
$execLabels = [
    'conforme_planejado' => 'Conforme planejado',
    'parcial' => 'Parcial',
    'alterado' => 'Alterado',
    'nao_realizada' => 'Não realizada',
];
$statusLabels = ['rascunho' => 'Rascunho', 'finalizada' => 'Finalizada', 'cancelada' => 'Não realizada'];
$statusClasses = [
    'rascunho' => 'bg-blue-100 text-blue-800',
    'finalizada' => 'bg-green-100 text-green-800',
    'cancelada' => 'bg-red-100 text-red-800',
];
$freqLabels = [
    'presente' => ['Presente', 'bg-green-100 text-green-800'],
    'falta' => ['Falta', 'bg-red-100 text-red-800'],
    'falta_justificada' => ['Falta justificada', 'bg-amber-100 text-amber-800'],
    'atraso' => ['Atraso', 'bg-orange-100 text-orange-800'],
];
$status = (string) ($aula['status'] ?? 'rascunho');
$resumo = $aula['resumo_frequencia'] ?? [];
$voltarQuery = http_build_query(['inicio' => date('Y-m-01', strtotime((string) $aula['data_aula'])), 'fim' => $aula['data_aula']]);
?>
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/diario?<?= htmlspecialchars($voltarQuery) ?>"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Detalhe da Aula</h2>
            <p class="text-sm text-gray-600">
                <?= htmlspecialchars((string) $aula['turma_nome']) ?> &middot; <?= htmlspecialchars((string) $aula['materia_nome']) ?>
                &middot; <?= date('d/m/Y', strtotime((string) $aula['data_aula'])) ?>
            </p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden divide-y divide-gray-200">
    <section class="p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Informações da aula</h3>
            <p class="mt-1 text-sm text-gray-500">Dados registrados pelo professor responsável.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</span>
                <span class="mt-1 block text-sm text-gray-900"><?= htmlspecialchars((string) $aula['professor_nome']) ?></span>
            </div>
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Horário</span>
                <span class="mt-1 block text-sm text-gray-900"><?= htmlspecialchars(substr((string) $aula['horario_de'], 0, 5)) ?> – <?= htmlspecialchars(substr((string) $aula['horario_ate'], 0, 5)) ?></span>
            </div>
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</span>
                <span class="mt-1 inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusClasses[$status] ?? 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></span>
            </div>
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Execução</span>
                <span class="mt-1 block text-sm text-gray-900"><?= htmlspecialchars($execLabels[(string) ($aula['execucao'] ?? '')] ?? '—') ?></span>
            </div>
            <div class="md:col-span-2">
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider">Finalizada em</span>
                <span class="mt-1 block text-sm text-gray-900"><?= !empty($aula['finalizada_at']) ? date('d/m/Y H:i', strtotime((string) $aula['finalizada_at'])) : '—' ?></span>
            </div>
        </div>
    </section>

    <section class="p-6 space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Conteúdo</h3>
            <p class="mt-1 text-sm text-gray-500">Conteúdo efetivamente ministrado e observações.</p>
        </div>
        <div>
            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Conteúdo realizado</span>
            <p class="text-sm text-gray-900 whitespace-pre-line"><?= htmlspecialchars((string) ($aula['conteudo_realizado'] ?? '')) ?: '<span class="text-gray-400">Não informado</span>' ?></p>
        </div>
        <?php if (!empty($aula['observacoes'])): ?>
        <div>
            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Observações</span>
            <p class="text-sm text-gray-900 whitespace-pre-line"><?= htmlspecialchars((string) $aula['observacoes']) ?></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($aula['plano_titulo'])): ?>
        <div class="rounded-lg border border-gray-200 bg-gray-50/70 p-4">
            <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Plano de aula vinculado</span>
            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) $aula['plano_titulo']) ?></p>
            <?php if (!empty($aula['plano_conteudo'])): ?>
                <p class="mt-1 text-sm text-gray-700 whitespace-pre-line"><?= htmlspecialchars((string) $aula['plano_conteudo']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="p-6 space-y-4">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Chamada</h3>
                <p class="mt-1 text-sm text-gray-500">Frequência registrada por aluno.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="px-2 py-1 rounded-full bg-green-100 text-green-800">Presentes: <?= (int) ($resumo['presente'] ?? 0) ?></span>
                <span class="px-2 py-1 rounded-full bg-red-100 text-red-800">Faltas: <?= (int) ($resumo['falta'] ?? 0) ?></span>
                <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-800">Justificadas: <?= (int) ($resumo['falta_justificada'] ?? 0) ?></span>
                <span class="px-2 py-1 rounded-full bg-orange-100 text-orange-800">Atrasos: <?= (int) ($resumo['atraso'] ?? 0) ?></span>
            </div>
        </div>
        <div class="overflow-x-auto -mx-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Observação</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach (($aula['frequencias'] ?? []) as $f):
                        $s = (string) ($f['situacao'] ?? '');
                        [$fLabel, $fClass] = $freqLabels[$s] ?? ['Sem registro', 'bg-slate-100 text-slate-600']; ?>
                        <tr>
                            <td class="px-6 py-3 text-sm text-gray-900"><?= htmlspecialchars((string) $f['nome']) ?></td>
                            <td class="px-6 py-3"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $fClass ?>"><?= htmlspecialchars($fLabel) ?></span></td>
                            <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars((string) ($f['observacao'] ?? '')) ?: '<span class="text-gray-300">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($aula['frequencias'])): ?>
                        <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum aluno ativo nesta turma.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
