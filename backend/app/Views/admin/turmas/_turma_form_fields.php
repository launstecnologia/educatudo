<?php
/**
 * Campos do formulário de turma (create/edit).
 *
 * Variáveis esperadas da view pai:
 * - $turma_form_item (array|null)
 * - $usaEstruturaNova, $cursosNovo, $seriesPorCurso, $ano_letivo_id, $cursos, $series
 */
$turma_form_item = $turma_form_item ?? null;
$isEdit = is_array($turma_form_item);
$turma = $isEdit ? $turma_form_item : [];
$usaEstruturaNova = !empty($cursosNovo);
$turma_curso_novo_id = $isEdit ? (int) ($turma['curso_novo_id'] ?? 0) : 0;
$turma_serie_id = $isEdit ? (int) ($turma['serie_id'] ?? 0) : 0;
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
$labelClass = 'block text-sm font-medium text-gray-700 mb-2';
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="md:col-span-2">
        <label for="nome" class="<?= $labelClass ?>">Nome da turma <span class="text-red-500">*</span></label>
        <input type="text" id="nome" name="nome" required
               value="<?= $isEdit ? htmlspecialchars($turma['nome'] ?? '') : '' ?>"
               placeholder="Ex.: 1ºA, 2ºB, 3ºA…"
               class="<?= $inputClass ?>">
        <p class="mt-1 text-xs text-gray-500">Identificador exibido nas listagens e na ficha da turma.</p>
    </div>

    <?php if ($usaEstruturaNova): ?>
    <input type="hidden" name="ano_letivo_id" value="<?= (int) $ano_letivo_id ?>">
    <?php if ($isEdit && !$turma_curso_novo_id && isset($turma['curso_id']) && (int) $turma['curso_id'] > 0): ?>
    <input type="hidden" name="curso_id" value="<?= (int) $turma['curso_id'] ?>">
    <input type="hidden" name="serie" value="<?= htmlspecialchars($turma['serie'] ?? '') ?>">
    <?php endif; ?>
    <div>
        <label for="curso_novo_id" class="<?= $labelClass ?>">Curso <span class="text-red-500">*</span></label>
        <select id="curso_novo_id" name="curso_novo_id" required class="<?= $inputClass ?>">
            <option value="">Selecione o curso</option>
            <?php foreach ($cursosNovo as $c): ?>
            <?php $possuiSerie = (int) ($c['possui_serie'] ?? 1); ?>
            <option value="<?= (int) $c['id'] ?>" data-possui-serie="<?= $possuiSerie ?>"
                <?= $turma_curso_novo_id === (int) $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nome']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-gray-500">Inclui cursos regulares e extras (ex.: Música, Robótica).</p>
    </div>
    <div id="wrap_serie_novo" class="hidden">
        <label for="serie_id" class="<?= $labelClass ?>">Série</label>
        <select id="serie_id" name="serie_id" class="<?= $inputClass ?>">
            <option value="">Selecione a série</option>
        </select>
    </div>

    <?php elseif (!empty($cursos)): ?>
    <?php $cursoSelecionado = $isEdit && isset($turma['curso_id']) ? (int) $turma['curso_id'] : 0; ?>
    <div class="md:col-span-2">
        <label for="curso_id" class="<?= $labelClass ?>">Curso / Série <span class="text-red-500">*</span></label>
        <select id="curso_id" name="curso_id" required class="<?= $inputClass ?>">
            <option value="">Selecione o curso/série</option>
            <?php foreach ($cursos as $curso): ?>
            <?php
            $tipoNome = trim((string) ($curso['tipo_nome'] ?? ''));
            $cursoNome = (string) ($curso['nome'] ?? '');
            $optionLabel = $tipoNome !== '' ? ($tipoNome . ' - ' . $cursoNome) : $cursoNome;
            $isSelected = $cursoSelecionado > 0 && $cursoSelecionado === (int) $curso['id']
                || ($cursoSelecionado === 0 && $isEdit && $cursoNome === (string) ($turma['serie'] ?? ''));
            ?>
            <option value="<?= (int) $curso['id'] ?>" data-serie="<?= htmlspecialchars($cursoNome) ?>"
                <?= $isSelected ? 'selected' : '' ?>>
                <?= htmlspecialchars($optionLabel) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php else: ?>
    <div class="md:col-span-2">
        <label for="serie" class="<?= $labelClass ?>">Série <span class="text-red-500">*</span></label>
        <select id="serie" name="serie" required class="<?= $inputClass ?>">
            <option value="">Selecione a série</option>
            <?php foreach ($series as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"
                <?= $isEdit && $s == ($turma['serie'] ?? '') ? 'selected' : '' ?>>
                <?= htmlspecialchars($s) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="md:col-span-2 flex items-center">
        <label class="flex items-center">
            <input type="checkbox" name="ativo" value="1"
                   <?= (!$isEdit || !empty($turma['ativo'])) ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
            <span class="ml-2 text-sm text-gray-700">Turma ativa</span>
        </label>
    </div>
</div>

<?php if ($usaEstruturaNova): ?>
<script>
(function () {
    var seriesPorCurso = <?= json_encode($seriesPorCurso ?? []) ?>;
    var turmaSerieId = <?= (int) $turma_serie_id ?>;
    var selCurso = document.getElementById('curso_novo_id');
    var wrapSerie = document.getElementById('wrap_serie_novo');
    var selSerie = document.getElementById('serie_id');
    if (!selCurso || !wrapSerie || !selSerie) return;
    function atualizarSerie() {
        var id = parseInt(selCurso.value, 10);
        var opt = selCurso.options[selCurso.selectedIndex];
        var possuiSerie = opt ? parseInt(opt.getAttribute('data-possui-serie') || '1', 10) : 0;
        selSerie.innerHTML = '<option value="">Selecione a série</option>';
        selSerie.removeAttribute('required');
        if (id && possuiSerie === 1 && seriesPorCurso[id]) {
            wrapSerie.classList.remove('hidden');
            seriesPorCurso[id].forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.nome;
                if (turmaSerieId && parseInt(s.id, 10) === turmaSerieId) o.selected = true;
                selSerie.appendChild(o);
            });
        } else {
            wrapSerie.classList.add('hidden');
        }
    }
    selCurso.addEventListener('change', function () { turmaSerieId = 0; atualizarSerie(); });
    atualizarSerie();
})();
</script>
<?php endif; ?>
