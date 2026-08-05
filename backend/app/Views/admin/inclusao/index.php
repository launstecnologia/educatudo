<?php
$statusBadge = static function (string $status): string {
    $map = [
        'rascunho'  => ['Rascunho', 'bg-gray-100 text-gray-700'],
        'ativa'     => ['Ativa', 'bg-green-100 text-green-700'],
        'suspensa'  => ['Suspensa', 'bg-amber-100 text-amber-700'],
        'encerrada' => ['Encerrada', 'bg-red-100 text-red-700'],
    ];
    [$label, $cls] = $map[$status] ?? ['—', 'bg-gray-100 text-gray-700'];
    return '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ' . $cls . '">' . $label . '</span>';
};
?>
<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 mb-1">EducaInclui — Acessibilidade</h1>
                <p class="text-sm text-gray-600">Máscara de acessibilidade por aluno (adaptações de acesso). As regras valem nas provas online assim que a máscara estiver <strong>ativa</strong>.</p>
            </div>
            <a href="<?= URL ?>/admin/inclusao/versoes" class="shrink-0 px-4 py-2 bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded-lg text-sm font-medium">Versões adaptadas &rarr;</a>
        </div>
    </div>

    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars((string) $flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <form method="get" action="<?= URL ?>/admin/inclusao" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="busca" value="<?= htmlspecialchars((string) ($busca ?? '')) ?>"
                   placeholder="Buscar aluno por nome para criar/editar máscara"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Buscar</button>
        </form>

        <?php if (!empty($alunos_encontrados)): ?>
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Alunos encontrados</p>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($alunos_encontrados as $al): ?>
                        <li class="flex items-center justify-between py-2">
                            <div>
                                <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $al['nome']) ?></span>
                                <span class="text-xs text-gray-500 ml-2"><?= htmlspecialchars((string) ($al['turma_nome'] ?? '')) ?></span>
                            </div>
                            <a href="<?= URL ?>/admin/inclusao/aluno/<?= (int) $al['id'] ?>" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Gerenciar máscara &rarr;</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Máscaras cadastradas</h2>
        </div>
        <?php if (empty($mascaras)): ?>
            <p class="px-6 py-8 text-sm text-gray-500 text-center">Nenhuma máscara cadastrada ainda. Busque um aluno acima para começar.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                            <th class="px-6 py-3">Aluno</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Tipo</th>
                            <th class="px-6 py-3">Vigência</th>
                            <th class="px-6 py-3">Regras</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($mascaras as $m): ?>
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900"><?= htmlspecialchars((string) ($m['aluno_nome'] ?? ('Aluno #' . (int) $m['aluno_id']))) ?></td>
                                <td class="px-6 py-3"><?= $statusBadge((string) $m['status']) ?></td>
                                <td class="px-6 py-3 text-gray-600"><?= $m['tipo_adaptacao'] === 'significativa' ? 'Significativa' : 'Acesso' ?></td>
                                <td class="px-6 py-3 text-gray-600">
                                    <?= !empty($m['data_inicio']) ? date('d/m/Y', strtotime((string) $m['data_inicio'])) : '—' ?>
                                    &ndash;
                                    <?= !empty($m['data_fim']) ? date('d/m/Y', strtotime((string) $m['data_fim'])) : '∞' ?>
                                </td>
                                <td class="px-6 py-3 text-gray-600"><?= (int) ($m['total_regras'] ?? 0) ?></td>
                                <td class="px-6 py-3 text-right">
                                    <a href="<?= URL ?>/admin/inclusao/aluno/<?= (int) $m['aluno_id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">Gerenciar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
