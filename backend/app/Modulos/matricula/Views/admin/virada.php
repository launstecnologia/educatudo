<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$anos_letivos = $anos_letivos ?? [];
$mapa = $mapa ?? [];
$ano_origem_id = (int) ($ano_origem_id ?? 0);
$ano_destino_id = (int) ($ano_destino_id ?? 0);

$page_header_title = 'Virada de ano';
$page_header_subtitle = 'Clone turmas do ano atual para o próximo, com sucessão de série.';
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Matrículas</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<form method="GET" action="<?= URL ?>/admin/enrollment/virada" class="bg-white rounded-xl shadow-lg p-6 mb-6 w-full">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ano de origem</label>
            <select name="ano_origem_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                <option value="">Selecione</option>
                <?php foreach ($anos_letivos as $al): ?>
                <option value="<?= (int) $al['id'] ?>" <?= $ano_origem_id === (int) $al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ano de destino</label>
            <select name="ano_destino_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                <option value="">Selecione</option>
                <?php foreach ($anos_letivos as $al): ?>
                <option value="<?= (int) $al['id'] ?>" <?= $ano_destino_id === (int) $al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-secondary">Ver mapa</button>
        </div>
    </div>
</form>

<?php if ($ano_origem_id > 0 && $ano_destino_id > 0): ?>
<div class="bg-white rounded-xl shadow-lg p-6 w-full space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold text-gray-800">Sucessão de turmas</h3>
        <form method="POST" action="<?= URL ?>/admin/enrollment/virada/clonar"
              onsubmit="return confirm('Clonar turmas do ano de origem para o destino? Turmas já ligadas serão ignoradas.')">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
            <input type="hidden" name="ano_origem_id" value="<?= $ano_origem_id ?>">
            <input type="hidden" name="ano_destino_id" value="<?= $ano_destino_id ?>">
            <button type="submit" class="btn-primary text-sm">
                <i class="fa-solid fa-copy mr-1.5"></i> Clonar turmas
            </button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">Turma origem</th>
                    <th class="px-4 py-2 text-left">Série atual</th>
                    <th class="px-4 py-2 text-left">Próxima série</th>
                    <th class="px-4 py-2 text-left">Turma destino</th>
                    <th class="px-4 py-2 text-left">Vagas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($mapa === []): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Nenhuma turma ativa no ano de origem.</td></tr>
                <?php else: ?>
                <?php foreach ($mapa as $linha):
                    $origem = $linha['origem'] ?? [];
                    $suc = $linha['sucessora'] ?? null;
                ?>
                <tr>
                    <td class="px-4 py-3 font-medium text-gray-900"><?= $esc($origem['nome'] ?? '') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= $esc($origem['serie'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-600">
                        <?php if (!empty($linha['conclui'])): ?>
                        <span class="text-amber-700">Conclui (sem série seguinte)</span>
                        <?php else: ?>
                        <?= $esc($linha['proxima_serie'] ?? ($origem['serie'] ?? '—')) ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($suc): ?>
                        <a href="<?= URL ?>/admin/turmas/<?= (int) $suc['id'] ?>" class="text-primary hover:underline"><?= $esc($suc['nome']) ?></a>
                        <?php else: ?>
                        <span class="text-gray-400">Ainda não clonada</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        <?= isset($origem['vagas']) && $origem['vagas'] !== null && (int) $origem['vagas'] > 0 ? (int) $origem['vagas'] : 'Ilimitado' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
