<?php
$contagens = $contagens ?? [];
$alunos = $alunos ?? [];
$csrf_token = $csrf_token ?? '';
$total = array_sum(array_map('intval', $contagens));
$autorizados = (int) ($contagens['Autorizado_total'] ?? 0) + (int) ($contagens['Autorizado_interno'] ?? 0);
?>
<div class="max-w-6xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="<?= URL ?>/admin/expo-colag" class="text-sm text-primary hover:underline">← Expo Colag</a>
            <h2 class="text-2xl font-bold text-gray-900 mt-1">Autorização de uso de imagem</h2>
            <p class="text-sm text-gray-600 mt-1">Caminho crítico para a galeria da Expo. Prazo sugerido: 18/09.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Alunos ativos</p>
            <p class="text-2xl font-bold text-gray-900"><?= (int) $total ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Autorizado total</p>
            <p class="text-2xl font-bold text-emerald-700"><?= (int) ($contagens['Autorizado_total'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Só interno</p>
            <p class="text-2xl font-bold text-amber-700"><?= (int) ($contagens['Autorizado_interno'] ?? 0) ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Sem autorização</p>
            <p class="text-2xl font-bold text-red-700"><?= (int) ($contagens['Nao_autorizado'] ?? 0) ?></p>
        </div>
    </div>

    <?php if ($total > 0): ?>
        <p class="text-sm text-gray-600">
            Cobertura: <strong><?= round(($autorizados / max(1, $total)) * 100) ?>%</strong> com algum aceite
            (<?= $autorizados ?> de <?= $total ?>).
        </p>
    <?php endif; ?>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Aluno</th>
                        <th class="px-4 py-3 font-medium">Turma</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Atualizar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($alunos)): ?>
                        <tr><td colspan="4" class="px-4 py-8 text-gray-500">Nenhum aluno ativo ou migration S2 ainda não aplicada.</td></tr>
                    <?php else: foreach ($alunos as $a): ?>
                        <?php
                        $st = (string) ($a['status'] ?? 'Nao_autorizado');
                        $badge = [
                            'Autorizado_total' => 'bg-emerald-100 text-emerald-800',
                            'Autorizado_interno' => 'bg-amber-100 text-amber-800',
                            'Nao_autorizado' => 'bg-red-100 text-red-800',
                        ][$st] ?? 'bg-slate-100 text-slate-700';
                        ?>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($a['aluno_nome'] ?? '') ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($a['turma_nome'] ?? '—') ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $badge ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $st)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <form method="post" action="<?= URL ?>/admin/expo-colag/autorizacoes" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="aluno_id" value="<?= (int) ($a['aluno_id'] ?? 0) ?>">
                                    <select name="status" class="border border-gray-300 rounded-lg px-2 py-1.5 bg-white text-xs">
                                        <option value="Autorizado_total" <?= $st === 'Autorizado_total' ? 'selected' : '' ?>>Autorizado total</option>
                                        <option value="Autorizado_interno" <?= $st === 'Autorizado_interno' ? 'selected' : '' ?>>Autorizado interno</option>
                                        <option value="Nao_autorizado" <?= $st === 'Nao_autorizado' ? 'selected' : '' ?>>Não autorizado</option>
                                    </select>
                                    <button type="submit" class="btn-primary-custom px-3 py-1.5 rounded-lg text-xs font-medium">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
