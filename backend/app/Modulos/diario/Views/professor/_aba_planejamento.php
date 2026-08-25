<?php
$aulasVinculaveis = array_values(array_filter(
    $aulas ?? [],
    static fn($a) => in_array((string) ($a['situacao'] ?? ''), ['pendente', 'rascunho', 'finalizada', 'nao_realizada'], true)
));
$statusPlano = [
    'rascunho' => 'Rascunho',
    'aprovado' => 'Aprovado',
    'rejeitado' => 'Rejeitado',
];
?>
<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <p class="text-sm text-gray-600">
            Os planos continuam sendo criados em
            <a href="<?= URL ?>/professor/planos-aula" class="font-semibold text-purple-700 hover:underline">Planos de Aula</a>.
            Aqui você só relaciona o que já existe com a aula realizada.
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Planos deste diário</h2>
            <p class="text-sm text-gray-500 mt-0.5">Turma e componente iguais aos do plano cadastrado.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php foreach (['Plano', 'Datas', 'Situação', 'Ação'] as $header): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($planos)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                Nenhum plano encontrado para esta turma e componente.
                                <a href="<?= URL ?>/professor/planos-aula/criar" class="text-purple-700 font-semibold hover:underline">Criar plano</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($planos as $plano):
                            $datas = $plano['datas'] ?? [];
                            $vinculos = $plano['aulas_vinculadas'] ?? [];
                            $relacionado = !empty($plano['relacionado']);
                        ?>
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $plano['titulo']) ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($statusPlano[(string) ($plano['status'] ?? '')] ?? (string) ($plano['status'] ?? '')) ?></p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?php if ($datas === []): ?>
                                        <span class="text-gray-300">—</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(implode(', ', array_map(static fn($d) => date('d/m', strtotime($d)), $datas))) ?>
                                    <?php endif; ?>
                                    <?php if ($vinculos !== []): ?>
                                        <p class="text-xs text-green-700 mt-1">
                                            Vinculado em
                                            <?= htmlspecialchars(implode(', ', array_map(static fn($a) => date('d/m', strtotime((string) $a['data_aula'])), $vinculos))) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $relacionado ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                                        <?= $relacionado ? 'Relacionado' : 'Não executado' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm space-y-2">
                                    <a href="<?= URL ?>/professor/planos-aula/visualizar/<?= (int) $plano['id'] ?>"
                                       class="block text-xs font-semibold text-purple-700 hover:underline" target="_blank" rel="noopener">
                                        Abrir plano
                                    </a>
                                    <?php if ($aulasVinculaveis !== []): ?>
                                        <form method="post" action="<?= URL ?>/professor/diarios/vincular-plano" class="flex flex-col sm:flex-row gap-2 items-start">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                            <input type="hidden" name="materia_id" value="<?= $materiaId ?>">
                                            <input type="hidden" name="inicio" value="<?= htmlspecialchars($inicio) ?>">
                                            <input type="hidden" name="fim" value="<?= htmlspecialchars($fim) ?>">
                                            <input type="hidden" name="plano_aula_id" value="<?= (int) $plano['id'] ?>">
                                            <select name="aula_alvo" required class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs bg-white">
                                                <option value="">Vincular à aula…</option>
                                                <?php foreach ($aulasVinculaveis as $aulaOpcao): ?>
                                                    <option value="<?= (int) $aulaOpcao['grade_horaria_id'] ?>|<?= htmlspecialchars((string) $aulaOpcao['data_aula']) ?>">
                                                        <?= date('d/m', strtotime((string) $aulaOpcao['data_aula'])) ?>
                                                        · <?= htmlspecialchars(substr((string) $aulaOpcao['horario_de'], 0, 5)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100">
                                                Vincular
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
