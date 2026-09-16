<?php
$item = is_array($item ?? null) ? $item : null;
$materias = is_array($materias ?? null) ? $materias : [];
$series = is_array($series ?? null) ? $series : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$anosLetivos = is_array($anos_letivos ?? null) ? $anos_letivos : [(int) date('Y')];
$schemaPronto = !empty($schema_pronto);
$ehEdicao = !empty($item['id']);
$idsMaterias = [];
foreach ((array) ($item['materias_ids'] ?? []) as $mid) {
    $mid = (int) $mid;
    if ($mid > 0) {
        $idsMaterias[$mid] = true;
    }
}
$idsSeries = [];
foreach ((array) ($item['series_ids'] ?? []) as $sid) {
    $sid = (int) $sid;
    if ($sid > 0) {
        $idsSeries[$sid] = true;
    }
}
$idsTurmas = [];
foreach ((array) ($item['turmas_ids'] ?? []) as $tid) {
    $tid = (int) $tid;
    if ($tid > 0) {
        $idsTurmas[$tid] = true;
    }
}
$finalidade = (($item['finalidade'] ?? 'oficial') === 'complementar') ? 'complementar' : 'oficial';
$anoSel = (int) ($item['ano_letivo'] ?? date('Y'));
$regrasAcademicas = is_array($regras_academicas ?? null) ? $regras_academicas : [];
$regraAcademicaId = (int) ($item['regra_academica_id'] ?? 0);
$criterios = is_array($criterios ?? null) ? $criterios : ['nota_minima_aprovacao' => 6.0, 'round_mode' => 'none', 'decimal_places' => 2];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= $ehEdicao ? 'Editar modelo de boletim' : 'Novo modelo de boletim' ?></h2>
            <p class="text-gray-600">Defina se é o oficial da vida escolar ou um extra, e quais disciplinas entram. A nota mínima vem da regra de aprovação. O cálculo das provas fica na Avaliação.</p>
        </div>
        <a href="<?= URL ?>/admin/boletins" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<?php if (!$schemaPronto): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
    Rode as migrations <code class="text-sm">2026_09_02_boletins.sql</code> e <code class="text-sm">2026_09_02_boletins_regra_academica.sql</code> no painel Master.
</div>
<?php else: ?>
<form method="POST" action="<?= URL ?>/admin/boletins<?= $ehEdicao ? '/' . (int) $item['id'] . '/update' : '' ?>" class="w-full">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
                <input type="text" name="nome" required maxlength="180"
                       value="<?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex.: Modelo oficial 2026">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
                <select name="ano_letivo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <?php foreach ($anosLetivos as $anoOpt): ?>
                        <option value="<?= (int) $anoOpt ?>" <?= $anoSel === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <span class="block text-sm font-medium text-gray-700 mb-2">Finalidade</span>
            <div class="space-y-2">
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="radio" name="finalidade" value="oficial" class="mt-0.5" <?= $finalidade === 'oficial' ? 'checked' : '' ?>>
                    <span><strong>Modelo oficial</strong> — matriz regular. Vai para o histórico e a vida escolar.</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="radio" name="finalidade" value="complementar" class="mt-0.5" <?= $finalidade === 'complementar' ? 'checked' : '' ?>>
                    <span><strong>Modelo extra</strong> — curso complementar (música, robótica…). Não entra no histórico oficial.</span>
                </label>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Regra de aprovação</label>
            <select name="regra_academica_id" id="boletim-regra-academica" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">Nenhuma — o motor escolhe pela série/curso na geração (fallback 6,0)</option>
                <?php foreach ($regrasAcademicas as $ra): ?>
                    <?php
                    $raId = (int) ($ra['id'] ?? 0);
                    $raLabel = (string) ($ra['nome'] ?? '');
                    if (!empty($ra['ano_letivo'])) {
                        $raLabel .= ' · ' . (int) $ra['ano_letivo'];
                    }
                    if (!empty($ra['curso_nome'])) {
                        $raLabel .= ' · ' . $ra['curso_nome'];
                    }
                    if (!empty($ra['serie_nome'])) {
                        $raLabel .= ' · ' . $ra['serie_nome'];
                    }
                    $raMin = isset($ra['media_minima']) ? number_format((float) $ra['media_minima'], 2, ',', '') : '6,00';
                    ?>
                    <option
                        value="<?= $raId ?>"
                        data-minima="<?= htmlspecialchars((string) ($ra['media_minima'] ?? '6'), ENT_QUOTES, 'UTF-8') ?>"
                        data-round="<?= htmlspecialchars((string) ($ra['round_mode'] ?? 'none'), ENT_QUOTES, 'UTF-8') ?>"
                        data-freq="<?= htmlspecialchars((string) ($ra['frequencia_minima'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        <?= $regraAcademicaId === $raId ? 'selected' : '' ?>
                    ><?= htmlspecialchars($raLabel . ' (mín. ' . $raMin . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">A média mínima, o arredondamento e a frequência saem desta regra — não se cadastram de novo aqui. <a href="<?= URL ?>/admin/regras-academicas" class="text-indigo-600 underline">Gerenciar regras</a></p>
            <p id="boletim-criterios-regra" class="text-sm text-gray-800 mt-2<?= $regraAcademicaId > 0 ? '' : ' hidden' ?>">
                Mínima: <strong id="boletim-criterio-minima"><?= htmlspecialchars(number_format((float) ($criterios['nota_minima_aprovacao'] ?? 6), 2, ',', ''), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="text-gray-400 mx-1">·</span>
                Arredondamento: <strong id="boletim-criterio-round"><?= (($criterios['round_mode'] ?? 'none') === 'half') ? 'faixa .00 / .50' : 'sem especial' ?></strong>
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="flex items-end gap-4 pb-2 flex-wrap md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="vis_aluno" value="1" class="rounded border-gray-300" <?= !isset($item['vis_aluno']) || (int) $item['vis_aluno'] === 1 ? 'checked' : '' ?>>
                    Aluno vê
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="vis_pais" value="1" class="rounded border-gray-300" <?= !isset($item['vis_pais']) || (int) $item['vis_pais'] === 1 ? 'checked' : '' ?>>
                    Pais veem
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="ativo" value="1" class="rounded border-gray-300" <?= !isset($item['ativo']) || (int) $item['ativo'] === 1 ? 'checked' : '' ?>>
                    Ativo
                </label>
            </div>
        </div>
        <input type="hidden" name="vis_coordenacao" value="1">

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Disciplinas deste modelo <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-2">Áreas oficiais (o pai). Os desdobramentos vêm do cadastro de Componentes Curriculares.</p>
            <div class="border border-gray-200 rounded-lg p-4 max-h-72 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($materias as $m): ?>
                        <?php $mid = (int) ($m['id'] ?? 0); if ($mid <= 0) { continue; } ?>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="materias_ids[]" value="<?= $mid ?>"
                                   class="rounded border-gray-300 text-indigo-600"
                                   <?= isset($idsMaterias[$mid]) ? 'checked' : '' ?>>
                            <span>
                                <?= htmlspecialchars((string) ($m['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($m['eh_rotulo'])): ?>
                                    <span class="text-xs text-gray-500">(área)</span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Séries (opcional)</label>
                <div class="border border-gray-200 rounded-lg p-4 max-h-56 overflow-y-auto">
                    <?php foreach ($series as $s): ?>
                        <?php $sid = (int) ($s['id'] ?? 0); if ($sid <= 0) { continue; } ?>
                        <label class="flex items-center gap-2 text-sm text-gray-800 mb-1">
                            <input type="checkbox" name="series_ids[]" value="<?= $sid ?>"
                                   class="rounded border-gray-300"
                                   <?= isset($idsSeries[$sid]) ? 'checked' : '' ?>>
                            <?= htmlspecialchars((string) ($s['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Turmas (opcional)</label>
                <div class="border border-gray-200 rounded-lg p-4 max-h-56 overflow-y-auto">
                    <?php foreach ($turmas as $t): ?>
                        <?php $tid = (int) ($t['id'] ?? 0); if ($tid <= 0) { continue; } ?>
                        <label class="flex items-center gap-2 text-sm text-gray-800 mb-1">
                            <input type="checkbox" name="turmas_ids[]" value="<?= $tid ?>"
                                   class="rounded border-gray-300"
                                   <?= isset($idsTurmas[$tid]) ? 'checked' : '' ?>>
                            <?= htmlspecialchars((string) ($t['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/boletins" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg text-white font-semibold"><?= $ehEdicao ? 'Salvar' : 'Cadastrar' ?></button>
        </div>
    </div>
</form>
<script>
(function () {
    var sel = document.getElementById('boletim-regra-academica');
    var box = document.getElementById('boletim-criterios-regra');
    var minEl = document.getElementById('boletim-criterio-minima');
    var roundEl = document.getElementById('boletim-criterio-round');
    if (!sel || !box) return;
    function atualizar() {
        var opt = sel.options[sel.selectedIndex];
        if (!sel.value || !opt) {
            box.classList.add('hidden');
            return;
        }
        var min = parseFloat(opt.getAttribute('data-minima') || '6');
        if (minEl) minEl.textContent = (isNaN(min) ? 6 : min).toFixed(2).replace('.', ',');
        if (roundEl) roundEl.textContent = opt.getAttribute('data-round') === 'half' ? 'faixa .00 / .50' : 'sem especial';
        box.classList.remove('hidden');
    }
    sel.addEventListener('change', atualizar);
})();
</script>
<?php endif; ?>
