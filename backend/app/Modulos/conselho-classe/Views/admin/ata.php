<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;
$sessao = is_array($sessao ?? null) ? $sessao : [];
$ata = is_array($ata ?? null) ? $ata : null;
$matriz = is_array($matriz ?? null) ? $matriz : [];
$csrf_token = $csrf_token ?? '';
$sid = (int) ($sessao['id'] ?? 0);
$participantes = is_array($matriz['participantes'] ?? null) ? $matriz['participantes'] : [];
$linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Ata do Conselho de Classe</h2>
            <p class="text-gray-600">Documento da reunião. Não confundir com Ata de Resultados Finais.</p>
        </div>
        <a href="<?= URL ?>/admin/conselhos/<?= $sid ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form method="POST" action="<?= URL ?>/admin/conselhos/<?= $sid ?>/ata">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Pauta</label>
            <textarea name="pauta" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars((string) (($ata['pauta'] ?? '') ?: ($sessao['pauta'] ?? ''))) ?></textarea>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Síntese</label>
            <textarea name="sintese" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars((string) ($ata['sintese'] ?? '')) ?></textarea>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Decisões e encaminhamentos</label>
            <textarea name="decisoes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars((string) ($ata['decisoes'] ?? '')) ?></textarea>
        </div>
        <div class="flex justify-end gap-3">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-semibold">Gerar / atualizar ata</button>
            <?php if ($ata): ?>
            <a href="<?= URL ?>/admin/conselhos/<?= $sid ?>/ata/pdf" class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">Baixar PDF</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-3">Prévia da reunião</h3>
    <p class="text-sm text-gray-600 mb-2">
        <?= htmlspecialchars((string) ($sessao['turma_nome'] ?? '')) ?>
        · <?= (int) ($sessao['bimestre'] ?? 0) ?>º Bimestre / <?= (int) ($sessao['ano_letivo'] ?? 0) ?>
        · <?= ConselhoService::statusLabel((string) ($sessao['status'] ?? '')) ?>
    </p>
    <p class="text-sm font-medium text-gray-800 mt-4 mb-2">Participantes presentes</p>
    <ul class="text-sm text-gray-700 list-disc pl-5 mb-4">
        <?php foreach ($participantes as $p): ?>
            <?php if (!empty($p['presente'])): ?>
            <li><?= htmlspecialchars((string) $p['nome']) ?> (<?= htmlspecialchars(ConselhoSessao::CARGOS[$p['cargo']] ?? $p['cargo']) ?>)</li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <p class="text-sm font-medium text-gray-800 mb-2">Situações</p>
    <ul class="text-sm text-gray-700 space-y-1">
        <?php foreach ($linhas as $linha):
            $homolog = $linha['resultado_homologado'] ?? null;
            $label = $homolog ? (ConselhoSessao::RESULTADOS[$homolog] ?? $homolog) : ($linha['resultado_preliminar']['label'] ?? '—');
        ?>
        <li><?= htmlspecialchars((string) $linha['aluno']['nome']) ?> — <?= htmlspecialchars((string) $label) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
