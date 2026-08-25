<?php
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
$anos = is_array($anos ?? null) ? $anos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$action = (string) ($filtros_action ?? URL . '/admin/resultados-finais');
$mostrarTurma = !empty($filtros_mostrar_turma);
$extraHidden = is_array($filtros_hidden ?? null) ? $filtros_hidden : [];
?>
<form method="GET" action="<?= htmlspecialchars($action) ?>" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
    <?php foreach ($extraHidden as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialchars((string) $k) ?>" value="<?= htmlspecialchars((string) $v) ?>">
    <?php endforeach; ?>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
        <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach ($anos as $ano): ?>
                <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
        <select name="periodo_tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach (ResultadoAcademico::PERIODO_TIPOS as $cod => $lab): ?>
                <option value="<?= htmlspecialchars($cod) ?>" <?= $periodoTipo === $cod ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Nº do período</label>
        <select name="periodo_numero" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <option value="0" <?= $periodoNumero === 0 ? 'selected' : '' ?>>— (ano inteiro)</option>
            <?php for ($n = 1; $n <= 4; $n++): ?>
                <option value="<?= $n ?>" <?= $periodoNumero === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <?php if ($mostrarTurma): ?>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Turma</label>
        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <option value="0">Todas</option>
            <?php foreach ($turmas as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
    </div>
</form>
