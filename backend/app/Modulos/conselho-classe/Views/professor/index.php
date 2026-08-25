<?php
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Services\ConselhoService;
$linhas = is_array($linhas ?? null) ? $linhas : [];
$anos = is_array($anos ?? null) ? $anos : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$bimestre = (int) ($bimestre ?? 1);
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Conselho de Classe</h1>
        <p class="text-gray-600 mt-1">Consulta das turmas em que você leciona. Deliberação fica com a coordenação.</p>
    </div>

    <?php if (!empty($flash_message)): ?>
    <div class="p-4 rounded-lg <?= ($flash_status ?? '') === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars((string) $flash_message) ?>
    </div>
    <?php endif; ?>

    <form method="GET" action="<?= URL ?>/professor/conselhos" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
            <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <?php foreach ($anos as $ano): ?>
                    <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
            <select name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <?php for ($b = 1; $b <= 4; $b++): ?>
                    <option value="<?= $b ?>" <?= $bimestre === $b ? 'selected' : '' ?>><?= $b ?>º Bimestre</option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pendências</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum Conselho nas suas turmas neste período.</td></tr>
                <?php else: ?>
                <?php foreach ($linhas as $linha):
                    $status = (string) ($linha['status_exibicao'] ?? 'nao_iniciado');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $linha['turma_nome']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) ($linha['total_alunos'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) (($linha['pendencias']['total'] ?? 0)) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars(ConselhoService::statusLabel($status)) ?></td>
                    <td class="px-6 py-4 text-right text-sm">
                        <?php if (!empty($linha['sessao_id'])): ?>
                            <a href="<?= URL ?>/professor/conselhos/<?= (int) $linha['sessao_id'] ?>" class="text-purple-700 font-semibold hover:underline">Ver matriz</a>
                        <?php else: ?>
                            <span class="text-gray-400">Não iniciado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
