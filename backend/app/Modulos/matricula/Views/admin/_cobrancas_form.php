<?php
/**
 * Seção de cobranças multi-tipo (finance_cobrancas).
 *
 * Variáveis esperadas:
 * - $planos_financeiros (array)
 * - $cobrancas_selecionadas (array tipo => plan_id)
 * - $selectClass (opcional) classes do <select>
 */
$esc = $esc ?? fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tiposCobranca = [
    'mensalidade'       => 'Mensalidade',
    'matricula'         => 'Matrícula',
    'material_didatico' => 'Material didático',
    'uniforme'          => 'Uniforme',
    'taxa'              => 'Taxa',
    'outros'            => 'Outros',
];
$cobrancas_selecionadas = $cobrancas_selecionadas ?? [];
$planos_financeiros     = $planos_financeiros ?? [];
$selectClass = $selectClass ?? 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm';
$mensalidadePlanId = (int)($cobrancas_selecionadas['mensalidade'] ?? 0);
?>
<input type="hidden" name="finance_plan_id" id="finance_plan_id"
       value="<?= $mensalidadePlanId > 0 ? $mensalidadePlanId : '' ?>">

<p class="text-xs text-gray-500 mb-3">
    Marque até uma cobrança por tipo e escolha o plano financeiro correspondente.
</p>

<div class="space-y-3" id="cobrancas-section">
<?php
$i = 0;
foreach ($tiposCobranca as $tipoKey => $tipoLabel):
    $planSel = (int)($cobrancas_selecionadas[$tipoKey] ?? 0);
    $checked = $planSel > 0;
?>
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 border border-gray-200 rounded-lg bg-gray-50/40"
         data-cobranca-row data-tipo="<?= $esc($tipoKey) ?>">
        <label class="flex items-center gap-2 sm:w-52 shrink-0 cursor-pointer">
            <input type="checkbox"
                   class="cobranca-incluir rounded border-gray-300 text-green-600 focus:ring-green-500"
                   <?= $checked ? 'checked' : '' ?>>
            <span class="text-sm font-medium text-gray-700"><?= $esc($tipoLabel) ?></span>
        </label>
        <div class="flex-1 min-w-0 cobranca-fields <?= $checked ? '' : 'opacity-50' ?>">
            <input type="hidden"
                   name="cobrancas[<?= $i ?>][tipo]"
                   value="<?= $esc($tipoKey) ?>"
                   class="cobranca-tipo"
                   <?= $checked ? '' : 'disabled' ?>>
            <select name="cobrancas[<?= $i ?>][plan_id]"
                    class="cobranca-plan <?= $esc($selectClass) ?>"
                    <?= $checked ? '' : 'disabled' ?>>
                <option value="">Selecionar plano...</option>
                <?php foreach ($planos_financeiros as $plano): ?>
                <option value="<?= (int)$plano['id'] ?>"
                    <?= $planSel === (int)$plano['id'] ? 'selected' : '' ?>>
                    <?= $esc($plano['nome'] ?? ('Plano #' . $plano['id'])) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
<?php
    $i++;
endforeach;
?>
</div>

<script>
(function () {
    var section = document.getElementById('cobrancas-section');
    if (!section || section.dataset.bound === '1') return;
    section.dataset.bound = '1';

    var financePlanInput = document.getElementById('finance_plan_id');

    function syncFinancePlanId() {
        if (!financePlanInput) return;
        var row = section.querySelector('[data-cobranca-row][data-tipo="mensalidade"]');
        if (!row) { financePlanInput.value = ''; return; }
        var cb = row.querySelector('.cobranca-incluir');
        var sel = row.querySelector('.cobranca-plan');
        if (cb && cb.checked && sel && sel.value) {
            financePlanInput.value = sel.value;
        } else {
            financePlanInput.value = '';
        }
    }

    function toggleRow(row) {
        var cb = row.querySelector('.cobranca-incluir');
        var tipo = row.querySelector('.cobranca-tipo');
        var plan = row.querySelector('.cobranca-plan');
        var fields = row.querySelector('.cobranca-fields');
        var on = !!(cb && cb.checked);
        if (tipo) tipo.disabled = !on;
        if (plan) {
            plan.disabled = !on;
            if (!on) plan.value = '';
        }
        if (fields) {
            fields.classList.toggle('opacity-50', !on);
        }
        syncFinancePlanId();
    }

    section.querySelectorAll('[data-cobranca-row]').forEach(function (row) {
        var cb = row.querySelector('.cobranca-incluir');
        var plan = row.querySelector('.cobranca-plan');
        if (cb) cb.addEventListener('change', function () { toggleRow(row); });
        if (plan) plan.addEventListener('change', syncFinancePlanId);
        toggleRow(row);
    });
})();
</script>
