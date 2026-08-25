<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;

$sessao = is_array($sessao ?? null) ? $sessao : [];
$matriz = is_array($matriz ?? null) ? $matriz : [];
$componentes = is_array($matriz['componentes'] ?? null) ? $matriz['componentes'] : [];
$linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
$csrf_token = $csrf_token ?? '';
$sid = (int) ($sessao['id'] ?? 0);
$status = (string) ($sessao['status'] ?? '');
$podeObs = in_array($status, ['em_andamento', 'reaberto'], true);
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex justify-between items-start gap-4 flex-wrap">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Conselho · <?= htmlspecialchars((string) ($sessao['turma_nome'] ?? '')) ?></h1>
            <p class="text-gray-600 mt-1"><?= (int) ($sessao['bimestre'] ?? 0) ?>º Bimestre / <?= (int) ($sessao['ano_letivo'] ?? 0) ?> · <?= htmlspecialchars(ConselhoService::statusLabel($status)) ?></p>
        </div>
        <a href="<?= URL ?>/professor/conselhos" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>

    <?php if (!empty($flash_message)): ?>
    <div class="p-4 rounded-lg <?= ($flash_status ?? '') === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars((string) $flash_message) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <?php foreach ($componentes as $comp): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><?= htmlspecialchars((string) $comp['nome']) ?></th>
                    <?php endforeach; ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preliminar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($linhas as $linha):
                    $aluno = $linha['aluno'];
                    $freq = $linha['frequencia']['percentual'] ?? null;
                    $prelim = $linha['resultado_preliminar'] ?? [];
                ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $aluno['nome']) ?></td>
                    <?php foreach ($componentes as $comp):
                        $chave = mb_strtolower((string) $comp['nome']);
                        $media = $linha['componentes'][$chave]['media'] ?? null;
                    ?>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= $media !== null ? number_format((float) $media, 1, ',', '.') : '—' ?></td>
                    <?php endforeach; ?>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= $freq !== null ? number_format((float) $freq, 1, ',', '.') . '%' : '—' ?></td>
                    <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($prelim['label'] ?? '—')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($podeObs && $linhas !== []): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Observação pedagógica</h2>
        <p class="text-sm text-gray-500 mb-4">Visível na ficha do Conselho. Não altera nota nem frequência.</p>
        <form method="POST" action="<?= URL ?>/professor/conselhos/<?= $sid ?>/observacao">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
                <select name="aluno_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione</option>
                    <?php foreach ($linhas as $linha): ?>
                        <option value="<?= (int) $linha['aluno']['id'] ?>"><?= htmlspecialchars((string) $linha['aluno']['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Texto</label>
                <textarea name="texto" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
            </div>
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Salvar observação</button>
        </form>
    </div>
    <?php endif; ?>
</div>
